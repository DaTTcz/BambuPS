<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\PrintJob;
use App\Models\Printer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\AppSetting;

class NotificationService
{
    public function __construct(private Printer $printer) {}

    /**
     * Začátek tisku - s náhledem desky a detaily ze souboru (pokud ho známe).
     */
    public function printStarted(PrintJob $job, array $status): void
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $lines = ["🖨️ <b>Tisk začal</b> na {$e($this->printer->name)}"];

        $name = $e($job->name);
        if ($job->plate_index && ($job->file?->metadata['plate_count'] ?? 1) > 1) {
            $name .= " (deska {$job->plate_index})";
        }
        $lines[] = "📄 {$name}";

        // Odhad času: zbývající čas z tiskárny je přesnější než odhad slicéru
        $remainingMin = (int) ($status['mc_remaining_time'] ?? 0);
        $seconds      = $remainingMin > 0 ? $remainingMin * 60 : $job->estimated_seconds;
        if ($seconds) {
            $lines[] = '⏱ ' . PrintJob::formatSeconds($seconds)
                . ' · hotovo kolem ' . now()->addSeconds($seconds)->format('H:i')
                . (now()->addSeconds($seconds)->isToday() ? '' : ' (' . now()->addSeconds($seconds)->format('d.m.') . ')');
        }

        if ($job->filament_g) {
            $types = collect($job->filaments ?? [])->pluck('type')->filter()->unique()->implode(', ');
            $lines[] = '🧵 ' . number_format($job->filament_g, 1, ',', ' ') . ' g' . ($types ? " {$e($types)}" : '');
        }

        $bed = array_filter([
            $job->bed_type ? $e($job->bed_type) : null,
            $job->bed_temp ? "{$job->bed_temp} °C" : null,
        ]);
        if ($bed) {
            $lines[] = '🛏️ ' . implode(' · ', $bed);
        }

        $layers = $job->total_layers ?? ($status['total_layer_num'] ?? null);
        $lh     = $job->file?->metadata['layer_height'] ?? null;
        if ($layers || $lh) {
            $lines[] = '📐 ' . implode(' · ', array_filter([
                $layers ? "{$layers} vrstev" : null,
                $lh ? "{$lh} mm" : null,
            ]));
        }

        $this->send('on_print_start', [
            'event'     => 'print_start',
            'printer'   => $this->printer->name,
            'file'      => $job->name,
            'plate'     => $job->plate_index,
            'estimated_seconds' => $seconds,
            'filament_g' => $job->filament_g,
            'message'   => implode("\n", $lines),
            '_image'    => $this->jobThumbnailPath($job),
        ]);
    }

    public function printDone(string $filename, ?PrintJob $job = null): void
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $lines = ["✅ <b>Tisk dokončen</b> na {$e($this->printer->name)}", "📄 {$e($job?->name ?? $filename)}"];
        if ($job?->duration_seconds) {
            $lines[] = '⏱ ' . PrintJob::formatSeconds($job->duration_seconds)
                . ($job->estimated_seconds ? ' (odhad ' . PrintJob::formatSeconds($job->estimated_seconds) . ')' : '');
        }
        if ($job?->filament_g) {
            $lines[] = '🧵 ' . number_format($job->filament_g, 1, ',', ' ') . ' g';
        }

        $this->send('on_print_done', [
            'event'    => 'print_done',
            'printer'  => $this->printer->name,
            'file'     => $job?->name ?? $filename,
            'message'  => implode("\n", $lines),
            '_image'   => $this->jobSnapshotPath($job) ?? $this->jobThumbnailPath($job),
        ]);
    }

    public function printFailed(string $filename, ?PrintJob $job = null): void
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $title = ($job?->status === PrintJob::STATUS_CANCELLED) ? '⏹ <b>Tisk zrušen</b>' : '❌ <b>Tisk selhal</b>';
        $lines = ["{$title} na {$e($this->printer->name)}", "📄 {$e($job?->name ?? $filename)}"];
        if ($job && $job->progress !== null) {
            $lines[] = "📊 {$job->progress} %" . ($job->layer_reached && $job->total_layers ? " · vrstva {$job->layer_reached}/{$job->total_layers}" : '');
        }
        $hms = collect($job?->hms ?? [])->map(fn ($h) => HmsService::decode($h)['message'] ?? null)->filter()->unique();
        if ($hms->isNotEmpty()) {
            $lines[] = '⚠️ ' . $e($hms->first());
        }

        $this->send('on_print_failed', [
            'event'    => 'print_failed',
            'printer'  => $this->printer->name,
            'file'     => $job?->name ?? $filename,
            'message'  => implode("\n", $lines),
            '_image'   => $this->jobSnapshotPath($job),
        ]);
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

    public function hmsError(array $hmsErrors): void
    {
        $messages = array_map(fn($h) => HmsService::decode($h)['message'], $hmsErrors);
        $text     = implode(', ', $messages);
        $this->send('on_hms_error', [
            'event'    => 'hms_error',
            'printer'  => $this->printer->name,
            'errors'   => $messages,
            'message'  => "⚠️ Varování tiskárny {$this->printer->name}: {$text}",
        ]);
    }

    public function filamentRunout(): void
    {
        $this->send('on_filament_runout', [
            'event'   => 'filament_runout',
            'printer' => $this->printer->name,
            'message' => "🧵 Konec filamentu v tiskárně {$this->printer->name}",
        ]);
    }

    private function send(string $trigger, array $payload): void
    {
        $settings = NotificationSetting::where('enabled', true)
            ->where($trigger, true)
            ->get();

        foreach ($settings as $setting) {
            try {
                match($setting->channel) {
                    'email'    => $this->sendEmail($setting, $payload),
                    'telegram' => $this->sendTelegram($setting, $payload),
                    'mqtt'     => $this->sendMqtt($setting, $payload),
                    default    => null,
                };
            } catch (\Exception $e) {
                Log::error("Notifikace [{$setting->channel}] selhala: " . $e->getMessage());
            }
        }
    }

    private function sendEmail(NotificationSetting $setting, array $payload): void
    {
        $address = $setting->config['address'] ?? null;
        if (!$address) return;

        // Aplikujeme SMTP config z DB
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

        $subjects = [
            'print_start'     => 'Tisk začal',
            'print_done'      => 'Tisk dokončen',
            'print_failed'    => 'Tisk selhal',
            'hms_error'       => 'Varování tiskárny',
            'filament_runout' => 'Konec filamentu',
        ];
        $subject = 'BambuPS – ' . ($subjects[$payload['event'] ?? ''] ?? 'Notifikace') . ' – ' . $this->printer->name;
        $text    = html_entity_decode(strip_tags($payload['message']), ENT_QUOTES);
        $image   = $payload['_image'] ?? null;

        Mail::raw($text, function($m) use ($address, $subject, $image) {
            $m->to($address)->subject($subject);
            if ($image && is_file($image)) {
                $m->attach($image, ['as' => 'nahled.' . pathinfo($image, PATHINFO_EXTENSION)]);
            }
        });
    }

    private function sendTelegram(NotificationSetting $setting, array $payload): void
    {
        $token  = $setting->config['bot_token'] ?? null;
        $chatId = $setting->config['chat_id']   ?? null;
        if (!$token || !$chatId) return;

        $image = $payload['_image'] ?? null;

        // S obrázkem jako fotka s popiskem (limit popisku 1024 znaků)
        if ($image && is_file($image) && mb_strlen($payload['message']) <= 1024) {
            $response = Http::timeout(20)
                ->attach('photo', file_get_contents($image), basename($image))
                ->post("https://api.telegram.org/bot{$token}/sendPhoto", [
                    'chat_id'    => $chatId,
                    'caption'    => $payload['message'],
                    'parse_mode' => 'HTML',
                ]);
            if ($response->successful()) {
                return;
            }
            Log::warning('Telegram sendPhoto selhal, posílám jen text: ' . $response->body());
        }

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $payload['message'],
            'parse_mode' => 'HTML',
        ]);
    }

    private function sendMqtt(NotificationSetting $setting, array $payload): void
    {
        $host  = $setting->config['host']  ?? null;
        $port  = (int)($setting->config['port'] ?? 1883);
        $topic = $setting->config['topic'] ?? 'bambups/events';
        if (!$host) return;

        $mqtt = new \PhpMqtt\Client\MqttClient($host, $port, 'bambups-notif-' . uniqid());
        $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings)
            ->setUsername($setting->config['username'] ?? null)
            ->setPassword($setting->config['password'] ?? null)
            ->setConnectTimeout(5);
        $mqtt->connect($connectionSettings);
        unset($payload['_image']);
        $payload['message'] = html_entity_decode(strip_tags($payload['message']), ENT_QUOTES);
        $mqtt->publish($topic, json_encode(array_merge($payload, [
            'time'    => now()->toISOString(),
            'printer' => $this->printer->name,
        ])));
        $mqtt->disconnect();
    }
}
