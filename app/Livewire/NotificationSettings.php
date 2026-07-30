<?php

namespace App\Livewire;

use App\Models\NotificationSetting;
use Livewire\Component;
use App\Models\AppSetting;

class NotificationSettings extends Component
{
    // Email
    public bool $emailEnabled       = false;
    public string $emailAddress     = '';
    public bool $emailOnDone        = true;
    public bool $emailOnFailed      = true;
    public bool $emailOnHms         = true;
    public bool $emailOnFilament    = true;

    // SMTP nastavení
    public string $smtpHost       = '';
    public string $smtpPort       = '587';
    public string $smtpUsername   = '';
    public string $smtpPassword   = '';
    public string $smtpEncryption = 'tls';
    public string $smtpFromEmail  = '';
    public string $smtpFromName   = 'BambuPS';

    // Telegram
    public bool $telegramEnabled    = false;
    public string $telegramBotToken = '';
    public string $telegramChatId   = '';
    public bool $telegramOnDone     = true;
    public bool $telegramOnFailed   = true;
    public bool $telegramOnHms      = true;
    public bool $telegramOnFilament = true;

    // MQTT
    public bool $mqttEnabled        = false;
    public string $mqttHost         = '';
    public string $mqttPort         = '1883';
    public string $mqttUsername     = '';
    public string $mqttPassword     = '';
    public string $mqttTopic        = 'bambups/events';
    public bool $mqttOnDone         = true;
    public bool $mqttOnFailed       = true;
    public bool $mqttOnHms          = true;
    public bool $mqttOnFilament     = true;

    public function mount(): void
    {
        $settings = NotificationSetting::where('user_id', auth()->id())->get()->keyBy('channel');

        if ($email = $settings->get('email')) {
            $this->emailEnabled    = $email->enabled;
            $this->emailAddress    = $email->config['address'] ?? '';
            $this->emailOnDone     = $email->on_print_done;
            $this->emailOnFailed   = $email->on_print_failed;
            $this->emailOnHms      = $email->on_hms_error;
            $this->emailOnFilament = $email->on_filament_runout;
	    $this->smtpHost       = AppSetting::get('smtp_host', '');
            $this->smtpPort       = AppSetting::get('smtp_port', '587');
            $this->smtpUsername   = AppSetting::get('smtp_username', '');
            $this->smtpPassword   = AppSetting::get('smtp_password', '');
            $this->smtpEncryption = AppSetting::get('smtp_encryption', 'tls');
            $this->smtpFromEmail  = AppSetting::get('smtp_from_email', '');
            $this->smtpFromName   = AppSetting::get('smtp_from_name', 'BambuPS');

        }

        if ($telegram = $settings->get('telegram')) {
            $this->telegramEnabled    = $telegram->enabled;
            $this->telegramBotToken   = $telegram->config['bot_token'] ?? '';
            $this->telegramChatId     = $telegram->config['chat_id']   ?? '';
            $this->telegramOnDone     = $telegram->on_print_done;
            $this->telegramOnFailed   = $telegram->on_print_failed;
            $this->telegramOnHms      = $telegram->on_hms_error;
            $this->telegramOnFilament = $telegram->on_filament_runout;
        }

        if ($mqtt = $settings->get('mqtt')) {
            $this->mqttEnabled    = $mqtt->enabled;
            $this->mqttHost       = $mqtt->config['host']     ?? '';
            $this->mqttPort       = $mqtt->config['port']     ?? '1883';
            $this->mqttUsername   = $mqtt->config['username'] ?? '';
            $this->mqttPassword   = $mqtt->config['password'] ?? '';
            $this->mqttTopic      = $mqtt->config['topic']    ?? 'bambups/events';
            $this->mqttOnDone     = $mqtt->on_print_done;
            $this->mqttOnFailed   = $mqtt->on_print_failed;
            $this->mqttOnHms      = $mqtt->on_hms_error;
            $this->mqttOnFilament = $mqtt->on_filament_runout;
        }
    }

    public function saveEmail(): void
    {
        $this->validate([
            'emailAddress' => 'required_if:emailEnabled,true|email|nullable',
        ]);

        NotificationSetting::updateOrCreate(
            ['user_id' => auth()->id(), 'channel' => 'email'],
            [
                'enabled'            => $this->emailEnabled,
                'config'             => ['address' => $this->emailAddress],
                'on_print_done'      => $this->emailOnDone,
                'on_print_failed'    => $this->emailOnFailed,
                'on_hms_error'       => $this->emailOnHms,
                'on_filament_runout' => $this->emailOnFilament,
            ]
        );
        $this->dispatch('toast', type: 'success', message: 'Email notifikace uloženy');
    }

    public function saveSmtp(): void
    {
        AppSetting::set('smtp_host',       $this->smtpHost);
        AppSetting::set('smtp_port',       $this->smtpPort);
        AppSetting::set('smtp_username',   $this->smtpUsername);
        AppSetting::set('smtp_password',   $this->smtpPassword);
        AppSetting::set('smtp_encryption', $this->smtpEncryption);
        AppSetting::set('smtp_from_email', $this->smtpFromEmail);
        AppSetting::set('smtp_from_name',  $this->smtpFromName);
        $this->dispatch('toast', type: 'success', message: 'SMTP nastavení uloženo');
    }

    private function applySmtpConfig(): void
    {
        if (!AppSetting::get('smtp_host')) return;
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

    public function saveTelegram(): void
    {
        NotificationSetting::updateOrCreate(
            ['user_id' => auth()->id(), 'channel' => 'telegram'],
            [
                'enabled'            => $this->telegramEnabled,
                'config'             => [
                    'bot_token' => $this->telegramBotToken,
                    'chat_id'   => $this->telegramChatId,
                ],
                'on_print_done'      => $this->telegramOnDone,
                'on_print_failed'    => $this->telegramOnFailed,
                'on_hms_error'       => $this->telegramOnHms,
                'on_filament_runout' => $this->telegramOnFilament,
            ]
        );
        $this->dispatch('toast', type: 'success', message: 'Telegram notifikace uloženy');
    }

    public function saveMqtt(): void
    {
        NotificationSetting::updateOrCreate(
            ['user_id' => auth()->id(), 'channel' => 'mqtt'],
            [
                'enabled'            => $this->mqttEnabled,
                'config'             => [
                    'host'     => $this->mqttHost,
                    'port'     => $this->mqttPort,
                    'username' => $this->mqttUsername,
                    'password' => $this->mqttPassword,
                    'topic'    => $this->mqttTopic,
                ],
                'on_print_done'      => $this->mqttOnDone,
                'on_print_failed'    => $this->mqttOnFailed,
                'on_hms_error'       => $this->mqttOnHms,
                'on_filament_runout' => $this->mqttOnFilament,
            ]
        );
        $this->dispatch('toast', type: 'success', message: 'MQTT notifikace uloženy');
    }

    public function testEmail(): void
    {
        if (!$this->emailEnabled || !$this->emailAddress) {
            $this->dispatch('toast', type: 'error', message: 'Email není nakonfigurován');
            return;
        }
        try {
            \Mail::raw('Test notifikace z BambuPS 🎉', function($m) {
                $m->to($this->emailAddress)->subject('BambuPS – Test notifikace');
            });
            $this->dispatch('toast', type: 'success', message: 'Testovací email odeslán');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Chyba: ' . $e->getMessage());
        }
    }

    public function testTelegram(): void
    {
        if (!$this->telegramEnabled || !$this->telegramBotToken || !$this->telegramChatId) {
            $this->dispatch('toast', type: 'error', message: 'Telegram není nakonfigurován');
            return;
        }
        try {
            $url = "https://api.telegram.org/bot{$this->telegramBotToken}/sendMessage";
            $response = \Http::post($url, [
                'chat_id' => $this->telegramChatId,
                'text'    => '🎉 Test notifikace z BambuPS – vše funguje!',
            ]);
            if ($response->successful()) {
                $this->dispatch('toast', type: 'success', message: 'Testovací zpráva odeslána');
            } else {
                $this->dispatch('toast', type: 'error', message: 'Chyba Telegram API: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Chyba: ' . $e->getMessage());
        }
    }

    public function testMqtt(): void
    {
        if (!$this->mqttEnabled || !$this->mqttHost) {
            $this->dispatch('toast', type: 'error', message: 'MQTT není nakonfigurováno');
            return;
        }
        try {
            $mqtt = new \PhpMqtt\Client\MqttClient($this->mqttHost, (int)$this->mqttPort);
            $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings)
                ->setUsername($this->mqttUsername ?: null)
                ->setPassword($this->mqttPassword ?: null)
                ->setConnectTimeout(5);
            $mqtt->connect($connectionSettings);
            $mqtt->publish($this->mqttTopic, json_encode([
                'event'   => 'test',
                'message' => 'Test notifikace z BambuPS',
                'time'    => now()->toISOString(),
            ]));
            $mqtt->disconnect();
            $this->dispatch('toast', type: 'success', message: 'MQTT zpráva odeslána');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Chyba MQTT: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.notification-settings');
    }
}
