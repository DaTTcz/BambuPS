<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\NotificationSetting;
use App\Models\PrintJob;
use App\Models\Printer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Odesílání notifikací. Text, předmět a přiložení obrázku si každý kanál
 * bere ze své šablony (viz NotificationTemplates), události jen připraví
 * proměnné.
 */
class NotificationService
{
    public function __construct(private ?Printer $printer = null) {}

    // ------------------------------------------------------------------
    // Události
    // ------------------------------------------------------------------

    /** Začátek tisku - s náhledem desky a detaily ze souboru (pokud ho známe). */
    public function printStarted(PrintJob $job, array $status): void
    {
        // Zbývající čas z tiskárny je přesnější než odhad slicéru
        $remainingMin = (int) ($status['mc_remaining_time'] ?? 0);
        $seconds      = $remainingMin > 0 ? $remainingMin * 60 : $job->estimated_seconds;

        $eta = '';
        if ($seconds) {
            $end = now()->local()->addSeconds($seconds);
            $eta = $end->format('H:i') . ($end->isToday() ? '' : ' (' . $end->format('d.m.') . ')');
        }

        $layers = $job->total_layers ?? ($status['total_layer_num'] ?? null);
        $lh     = $job->file?->metadata['layer_height'] ?? null;

        $vars = [
            'file'      => $job->name,
            'plate'     => $this->plateLabel($job),
            'estimated' => $seconds ? PrintJob::formatSeconds($seconds) : '',
            'eta'       => $eta,
            'filament'  => $this->filamentLabel($job),
            'bed'       => implode(' · ', array_filter([
                $job->bed_type,
                $job->bed_temp ? "{$job->bed_temp} °C" : null,
            ])),
            'layers'    => implode(' · ', array_filter([
                $layers ? "{$layers} vrstev" : null,
                $lh ? "{$lh} mm" : null,
            ])),
        ];

        $this->dispatchEvent('print_start', $vars, [
            'file'              => $job->name,
            'plate'             => $job->plate_index,
            'estimated_seconds' => $seconds,
            'filament_g'        => $job->filament_g,
        ], $this->jobThumbnailPath($job));
    }

    public function printDone(string $filename, ?PrintJob $job = null): void
    {
        $vars = [
            'file'           => $job?->name ?? $filename,
            'plate'          => $this->plateLabel($job),
            'duration'       => $job?->duration_seconds ? PrintJob::formatSeconds($job->duration_seconds) : '',
            'estimated'      => $job?->estimated_seconds ? PrintJob::formatSeconds($job->estimated_seconds) : '',
            'estimated_note' => $job?->estimated_seconds ? '(odhad ' . PrintJob::formatSeconds($job->estimated_seconds) . ')' : '',
            'filament'       => $this->filamentLabel($job),
        ];

        $this->dispatchEvent('print_done', $vars, [
            'file'             => $vars['file'],
            'duration_seconds' => $job?->duration_seconds,
            'filament_g'       => $job?->filament_g,
        ], $this->jobSnapshotPath($job) ?? $this->jobThumbnailPath($job));
    }

    public function printFailed(string $filename, ?PrintJob $job = null): void
    {
        $cancelled = $job?->status === PrintJob::STATUS_CANCELLED;
        $hms = collect($job?->hms ?? [])->map(fn ($h) => HmsService::decode($h)['message'] ?? null)->filter()->unique()->values();

        $progress = '';
        if ($job && $job->progress !== null) {
            $progress = "{$job->progress} %"
                . ($job->layer_reached && $job->total_layers ? " · vrstva {$job->layer_reached}/{$job->total_layers}" : '');
        }

        $vars = [
            'file'        => $job?->name ?? $filename,
            'plate'       => $this->plateLabel($job),
            'result'      => $cancelled ? 'Tisk zrušen' : 'Tisk selhal',
            'result_icon' => $cancelled ? '⏹' : '❌',
            'progress'    => $progress,
            'duration'    => $job?->duration_seconds ? PrintJob::formatSeconds($job->duration_seconds) : '',
            'error'       => $hms->first() ?? '',
        ];

        $this->dispatchEvent('print_failed', $vars, [
            'file'      => $vars['file'],
            'cancelled' => $cancelled,
            'progress'  => $job?->progress,
            'errors'    => $hms->all(),
        ], $this->jobSnapshotPath($job));
    }

    public function hmsError(array $hmsErrors): void
    {
        $messages = array_values(array_filter(array_map(fn ($h) => HmsService::decode($h)['message'] ?? null, $hmsErrors)));

        $this->dispatchEvent('hms_error', [
            'errors' => implode("\n", $messages),
            'error'  => $messages[0] ?? '',
        ], ['errors' => $messages]);
    }

    public function filamentRunout(): void
    {
        $job = $this->printer
            ? PrintJob::where('printer_id', $this->printer->id)->where('status', PrintJob::STATUS_RUNNING)->latest('id')->first()
            : null;

        $this->dispatchEvent('filament_runout', ['file' => $job?->name ?? ''], ['file' => $job?->name]);
    }

    /**
     * Zkušební odeslání šablony s ukázkovými daty (z editoru v nastavení).
     * Výjimky nechává probublat, ať je editor může ukázat.
     */
    public function sendTest(NotificationSetting $setting, string $event, array $template): void
    {
        $vars = array_merge(NotificationTemplates::SAMPLE, [
            'date' => now()->local()->format('d.m.Y'),
            'time' => now()->local()->format('H:i'),
        ]);

        // Jako obrázek poslouží náhled z posledního tisku, který nějaký má
        $image = null;
        foreach (PrintJob::with('file')->latest('id')->limit(20)->get() as $job) {
            if ($image = $this->jobSnapshotPath($job) ?? $this->jobThumbnailPath($job)) {
                break;
            }
        }

        $this->deliver($setting, $event, $vars, ['test' => true], $image, $template);
    }

    // ------------------------------------------------------------------
    // Proměnné
    // ------------------------------------------------------------------

    private function plateLabel(?PrintJob $job): string
    {
        if ($job?->plate_index && ($job->file?->metadata['plate_count'] ?? 1) > 1) {
            return "(deska {$job->plate_index})";
        }
        return '';
    }

    private function filamentLabel(?PrintJob $job): string
    {
        if (!$job?->filament_g) {
            return '';
        }
        $types = collect($job->filaments ?? [])->pluck('type')->filter()->unique()->implode(', ');
        return number_format($job->filament_g, 1, ',', ' ') . ' g' . ($types ? " {$types}" : '');
    }

    /** Náhled desky ze souboru (PNG ze slicéru) */
    private function jobThumbnailPath(?PrintJob $job): ?string
    {
        $file = $job?->file;
        if (!$file) {
            return null;
        }
        $plate = collect($file->metadata['plates'] ?? [])->firstWhere('index', $job->plate_index);
        $rel   = $plate['thumbnail_path'] ?? $file->thumbnail_path;
        return ($rel && Storage::disk('local')->exists($rel)) ? Storage::disk('local')->path($rel) : null;
    }

    /** Snímek z kamery na konci tisku */
    private function jobSnapshotPath(?PrintJob $job): ?string
    {
        $rel = $job?->snapshot_path;
        return ($rel && Storage::disk('local')->exists($rel)) ? Storage::disk('local')->path($rel) : null;
    }

    // ------------------------------------------------------------------
    // Rozeslání
    // ------------------------------------------------------------------

    /**
     * @param array       $vars  proměnné pro šablonu (printer/date/time se doplní)
     * @param array       $data  strukturovaná data navíc pro MQTT
     * @param string|null $image cesta k obrázku (pošle se, jen když ho šablona chce)
     */
    private function dispatchEvent(string $event, array $vars, array $data = [], ?string $image = null): void
    {
        $trigger = NotificationTemplates::event($event)['trigger'] ?? null;
        if (!$trigger) {
            return;
        }

        $vars = array_merge([
            'printer' => $this->printer?->name ?? '',
            'date'    => now()->local()->format('d.m.Y'),
            'time'    => now()->local()->format('H:i'),
        ], $vars);

        $settings = NotificationSetting::where('enabled', true)->where($trigger, true)->get();

        foreach ($settings as $setting) {
            try {
                $this->deliver($setting, $event, $vars, $data, $image);
            } catch (\Throwable $e) {
                Log::error("Notifikace [{$setting->channel}] {$event} selhala: " . $e->getMessage());
            }
        }
    }

    private function deliver(NotificationSetting $setting, string $event, array $vars, array $data, ?string $image, ?array $template = null): void
    {
        $tpl = $template ?? NotificationTemplates::forChannel($setting->config, $event);
        $img = !empty($tpl['image']) && $image && is_file($image) ? $image : null;

        match ($setting->channel) {
            'email'    => $this->sendEmail($setting, $tpl, $vars, $img),
            'telegram' => $this->sendTelegram($setting, $tpl, $vars, $img),
            'mqtt'     => $this->sendMqtt($setting, $event, $tpl, $vars, $data),
            default    => null,
        };
    }

    private function sendEmail(NotificationSetting $setting, array $tpl, array $vars, ?string $image): void
    {
        $address = $setting->config['address'] ?? null;
        if (!$address) {
            throw new \RuntimeException('Chybí e-mailová adresa.');
        }

        // SMTP z nastavení appky
        if (AppSetting::get('smtp_host')) {
            config([
                'mail.mailers.smtp.host'       => AppSetting::get('smtp_host'),
                'mail.mailers.smtp.port'       => AppSetting::get('smtp_port', 587),
                'mail.mailers.smtp.username'   => AppSetting::get('smtp_username'),
                'mail.mailers.smtp.password'   => AppSetting::get('smtp_password'),
                'mail.mailers.smtp.encryption' => AppSetting::get('smtp_encryption', 'tls'),
                'mail.from.address'            => AppSetting::get('smtp_from_email'),
                'mail.from.name'               => AppSetting::get('smtp_from_name', 'BambuPS'),
                'mail.default'                 => 'smtp',
            ]);
        }

        $subject = trim(preg_replace('/\s+/', ' ', NotificationTemplates::render($tpl['subject'] ?: 'BambuPS – {printer}', $vars, false)));
        $text    = NotificationTemplates::render($tpl['body'], $vars, false);

        Mail::raw($text, function ($m) use ($address, $subject, $image) {
            $m->to($address)->subject($subject ?: 'BambuPS');
            if ($image) {
                $m->attach($image, ['as' => 'nahled.' . pathinfo($image, PATHINFO_EXTENSION)]);
            }
        });
    }

    private function sendTelegram(NotificationSetting $setting, array $tpl, array $vars, ?string $image): void
    {
        $token  = $setting->config['bot_token'] ?? null;
        $chatId = $setting->config['chat_id'] ?? null;
        if (!$token || !$chatId) {
            throw new \RuntimeException('Chybí Bot Token nebo Chat ID.');
        }

        $message = NotificationTemplates::render($tpl['body'], $vars, true);
        $api     = "https://api.telegram.org/bot{$token}";

        // S obrázkem jako fotka s popiskem (limit popisku 1024 znaků)
        if ($image && mb_strlen($message) <= 1024) {
            $response = Http::timeout(20)
                ->attach('photo', file_get_contents($image), basename($image))
                ->post("{$api}/sendPhoto", ['chat_id' => $chatId, 'caption' => $message, 'parse_mode' => 'HTML']);
            if ($response->successful()) {
                return;
            }
            Log::warning('Telegram sendPhoto selhal, posílám jen text: ' . $response->body());
        }

        $response = Http::timeout(15)->post("{$api}/sendMessage", [
            'chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'HTML',
        ]);

        // Rozbité HTML ve vlastní šabloně -> pošli aspoň čistý text
        if (!$response->successful() && str_contains($response->body(), 'parse entities')) {
            $response = Http::timeout(15)->post("{$api}/sendMessage", [
                'chat_id' => $chatId,
                'text'    => NotificationTemplates::render($tpl['body'], $vars, false),
            ]);
        }

        if (!$response->successful()) {
            throw new \RuntimeException('Telegram API: ' . $response->body());
        }
    }

    private function sendMqtt(NotificationSetting $setting, string $event, array $tpl, array $vars, array $data): void
    {
        $host  = $setting->config['host'] ?? null;
        $port  = (int) ($setting->config['port'] ?? 1883);
        $topic = $setting->config['topic'] ?? 'bambups/events';
        if (!$host) {
            throw new \RuntimeException('Chybí MQTT host.');
        }

        $mqtt = new \PhpMqtt\Client\MqttClient($host, $port, 'bambups-notif-' . uniqid());
        $mqtt->connect((new \PhpMqtt\Client\ConnectionSettings)
            ->setUsername(($setting->config['username'] ?? '') ?: null)
            ->setPassword(($setting->config['password'] ?? '') ?: null)
            ->setConnectTimeout(5));

        $mqtt->publish($topic, json_encode(array_merge($data, [
            'event'   => $event,
            'printer' => $vars['printer'] ?? '',
            'message' => NotificationTemplates::render($tpl['body'], $vars, false),
            'vars'    => $vars,
            'time'    => now()->toISOString(),
        ]), JSON_UNESCAPED_UNICODE));
        $mqtt->disconnect();
    }
}
