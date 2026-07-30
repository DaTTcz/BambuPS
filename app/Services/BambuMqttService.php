<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Printer;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Exceptions\MqttClientException;

class BambuMqttService
{
    private MqttClient $client;
    private Printer $printer;
    private int $port;
    private int $connectTimeout;

    public function __construct(Printer $printer)
    {
        $this->printer = $printer;

        $config               = Module::getConfig('mqtt_connector');
        $this->port           = (int) ($config['port'] ?? 8883);
        $this->connectTimeout = (int) ($config['connect_timeout'] ?? 5);
    }

    public function connect(): bool
    {
        try {
            $this->client = new MqttClient(
                $this->printer->ip_address,
                $this->port,
                'bambups_' . $this->printer->id . '_' . uniqid(),
                MqttClient::MQTT_3_1_1,
            );

            $settings = (new ConnectionSettings())
                ->setUseTls(true)
                ->setTlsSelfSignedAllowed(true)
                ->setTlsVerifyPeer(false)
                ->setTlsVerifyPeerName(false)
                ->setUsername('bblp')
                ->setPassword($this->printer->access_code)
                ->setConnectTimeout($this->connectTimeout)
                ->setSocketTimeout($this->connectTimeout);

            $this->client->connect($settings, true);

            return true;
        } catch (MqttClientException $e) {
            \Log::error('MQTT connect error for printer ' . $this->printer->id . ': ' . $e->getMessage());
            return false;
        }
    }

    public function subscribe(callable $callback): void
    {
        $topic = 'device/' . $this->printer->serial_number . '/report';

        $this->client->subscribe($topic, function (string $topic, string $message) use ($callback) {
            $data = @json_decode($message, true);
            if ($data) {
                $callback($data);
            }
        }, 0);
    }

    public function publish(array $payload, int $qos = 0): bool
    {
        try {
            $topic   = 'device/' . $this->printer->serial_number . '/request';
            $message = json_encode($payload);
            $this->client->publish($topic, $message, $qos);
            return true;
        } catch (MqttClientException $e) {
            \Log::error('MQTT publish error: ' . $e->getMessage());
            return false;
        }
    }

    public function getClient(): MqttClient
    {
        return $this->client;
    }

    public function disconnect(): void
    {
        try {
            $this->client->disconnect();
        } catch (MqttClientException $e) {
            // ignore
        }
    }

    public function requestStatus(): bool
    {
        return $this->publish([
            'pushing' => [
                'sequence_id' => '0',
                'command'     => 'pushall',
            ],
        ]);
    }
}
