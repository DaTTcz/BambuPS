<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\Printer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\AppSetting;

class NotificationService
{
    public function __construct(private Printer $printer) {}

    public function printDone(string $filename): void
    {
        $this->send('on_print_done', [
            'event'    => 'print_done',
            'printer'  => $this->printer->name,
            'file'     => $filename,
            'message'  => "✅ Tisk dokončen na {$this->printer->name}: {$filename}",
        ]);
    }

    public function printFailed(string $filename): void
    {
        $this->send('on_print_failed', [
            'event'    => 'print_failed',
            'printer'  => $this->printer->name,
            'file'     => $filename,
            'message'  => "❌ Tisk selhal na {$this->printer->name}: {$filename}",
        ]);
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

        Mail::raw($payload['message'], function($m) use ($address, $payload) {
            $m->to($address)->subject('BambuPS – ' . ($payload['event'] ?? 'Notifikace'));
        });
    }

    private function sendTelegram(NotificationSetting $setting, array $payload): void
    {
        $token  = $setting->config['bot_token'] ?? null;
        $chatId = $setting->config['chat_id']   ?? null;
        if (!$token || !$chatId) return;

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
        $mqtt->publish($topic, json_encode(array_merge($payload, [
            'time'    => now()->toISOString(),
            'printer' => $this->printer->name,
        ])));
        $mqtt->disconnect();
    }
}
