<?php

namespace App\Services;

use App\Models\Printer;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class PrinterCommandService
{
    private Printer $printer;

    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
    }
    //Spuštění tisku
    public function startPrint(
        string $filename,
        int $plateIndex = 1,
        bool $useAms = true,
        string $bedType = 'auto',
        array $amsMapping = [],
        bool $bedLeveling = true,
        bool $timelapse = false,
        bool $layerInspect = false,
        bool $flowCali = false,
        bool $vibrationCali = false,
    ): bool {
        // ams_mapping - KLÍČOVÉ: pozice v poli NENÍ zvolený fyzický slot,
        // je to "extruder_index" zapsaný v samotném gcode (M620 SxA), na
        // který se firmware ptá při tisku. Hodnota na téhle pozici říká,
        // KTERÝ fyzický AMS slot se má pro tenhle index doopravdy použít.
        // Tohle umožňuje vybrat LIBOVOLNÝ slot se stejným materiálem, ne
        // jen ten, pro který byl soubor původně naslicovaný - dřív appka
        // dělala position=hodnota=stejný slot (identitu), což fungovalo
        // jen náhodou, když uživatel zvolil přesně ten původní slot.
        $maxIndex = 0;
        foreach ($amsMapping as $m) {
            $maxIndex = max($maxIndex, (int) ($m['extruder_index'] ?? 0));
        }
        // Zaokrouhlit nahoru na násobek 4 (Bambu očekává délku podle počtu
        // AMS jednotek, min. 4 - jeden AMS má 4 sloty).
        $totalSlots = max(4, (intdiv($maxIndex, 4) + 1) * 4);

        $flatAmsMapping = array_fill(0, $totalSlots, -1);
        $amsMapping2    = array_fill(0, $totalSlots, ['ams_id' => 255, 'slot_id' => 255]);

        foreach (array_values($amsMapping) as $m) {
            $amsId         = (int) $m['ams'];
            $slotId        = (int) $m['slot'];
            $trayId        = $amsId * 4 + $slotId;
            $extruderIndex = (int) ($m['extruder_index'] ?? 0);

            $flatAmsMapping[$extruderIndex] = $trayId;
            $amsMapping2[$extruderIndex]    = ['ams_id' => $amsId, 'slot_id' => $slotId];
        }

        // Unikátní ID pro project_id/subtask_id/task_id - "0" pro všechny
        // příkazy způsobuje, že tiskárna bere opakovaný tisk jako pokračování
        // předchozí (často FAILED) úlohy. Bambu Studio posílá vždy čerstvé ID.
        $submissionId = (string) ((int) (microtime(true) * 1000) % 2147483647 ?: 1);

        $payload = [
            'print' => [
                'sequence_id'    => '20000',
                'command'        => 'project_file',
                'param'          => "Metadata/plate_{$plateIndex}.gcode",
                // "ftp://" schéma, ne "file:///sdcard/" - takhle to posílá
                // Bambu Studio i ověřené třetí strany.
                'url'            => "ftp://{$filename}",
                'file'           => $filename,
                'md5'            => '',
                'bed_type'       => $bedType,
                'timelapse'      => $timelapse,
                'bed_leveling'   => $bedLeveling,
                'auto_bed_leveling' => $bedLeveling ? 1 : 0,
                'flow_cali'      => $flowCali,
                'extrude_cali_flag' => $flowCali ? 1 : 0,
                'extrude_cali_manual_mode' => 0,
                'nozzle_offset_cali' => 0,
                'vibration_cali' => $vibrationCali,
                'layer_inspect'  => $layerInspect,
                'use_ams'        => $useAms,
                'cfg'            => '0',
                'subtask_name'   => pathinfo($filename, PATHINFO_FILENAME),
                'profile_id'     => '0',
                'project_id'     => $submissionId,
                'subtask_id'     => $submissionId,
                'task_id'        => $submissionId,
            ],
        ];

        if (!empty($amsMapping)) {
            $payload['print']['ams_mapping']  = $flatAmsMapping;
            $payload['print']['ams_mapping2'] = $amsMapping2;
        }

        return $this->send($payload);
    }

    // Explicitní příkaz pro natažení filamentu z konkrétního AMS slotu.
    // Potřebné jako "dokopnutí" po project_file na firmwarech, kde project_file
    // samo o sobě AMS handling nespustí (ověřeno ručním testem na X1E).
    public function changeAmsFilament(int $amsId, int $slotId): bool
    {
        $trayId = $amsId * 4 + $slotId;
        return $this->send([
            'print' => [
                'sequence_id' => (string) time(),
                'command'     => 'ams_change_filament',
                'ams_id'      => $amsId,
                'slot_id'     => $slotId,
                'target'      => $trayId,
                'curr_temp'   => -1,
                'tar_temp'    => -1,
            ],
        ]);
    }

    public function send(array $payload): bool
    {
        try {
            $client = new MqttClient(
                $this->printer->ip_address,
                8883,
                'bambups_cmd_' . uniqid(),
                MqttClient::MQTT_3_1_1,
            );

            $settings = (new ConnectionSettings())
                ->setUseTls(true)
                ->setTlsSelfSignedAllowed(true)
                ->setTlsVerifyPeer(false)
                ->setTlsVerifyPeerName(false)
                ->setUsername('bblp')
                ->setPassword($this->printer->access_code)
                ->setConnectTimeout(5)
                ->setSocketTimeout(5);

            $client->connect($settings, true);

            $topic = 'device/' . $this->printer->serial_number . '/request';
            \Log::info('MQTT CMD topic=' . $topic . ' payload=' . json_encode($payload));
            // QoS 1 (ne 0!) - tiskárna při vysílání status zpráv QoS 0 příkazy
            // tiše zahazuje. Bez tohohle appka ztrácela příkazy pro AMS/materiál.
            $client->publish($topic, json_encode($payload), 1);
            $client->disconnect();

            return true;
        } catch (\Exception $e) {
            \Log::error('PrinterCommand error: ' . $e->getMessage());
            return false;
        }
    }

    // Světlo
    public function setLight(string $node, string $mode): bool
    {
        return $this->send([
            'system' => [
                'sequence_id'   => (string) time(),
                'command'       => 'ledctrl',
                'led_node'      => $node,
                'led_mode'      => $mode,
                'led_on_time'   => 500,
                'led_off_time'  => 500,
                'loop_times'    => 1,
                'interval_time' => 1000,
            ],
        ]);
    }

    // Teplota hotendu
    public function setHotendTemp(int $temp): bool
    {
        return $this->sendGcode("M104 S{$temp}\n");
    }

    // Teplota podložky
    public function setBedTemp(int $temp): bool
    {
        return $this->sendGcode("M140 S{$temp}\n");
    }

    // Ventilátor (P1=cooling, P2=aux, P3=chamber) 0-100%
    public function setFan(int $fanIndex, int $percent): bool
    {
        $speed = (int) round($percent * 255 / 100);
        return $this->sendGcode("M106 P{$fanIndex} S{$speed}\n");
    }

    // Rychlost tisku v %
    public function setPrintSpeed(int $percent): bool
    {
        return $this->sendGcode("M220 S{$percent}\n");
    }

    // Pause
    public function pause(): bool
    {
        return $this->send([
            'print' => ['sequence_id' => '0', 'command' => 'pause'],
        ]);
    }

    // Resume
    public function resume(): bool
    {
        return $this->send([
            'print' => ['sequence_id' => '0', 'command' => 'resume'],
        ]);
    }

    // Stop
    public function stop(): bool
    {
        return $this->send([
            'print' => ['sequence_id' => '0', 'command' => 'stop'],
        ]);
    }

    private function sendGcode(string $gcode): bool
    {
        return $this->send([
            'print' => [
                'sequence_id' => '0',
                'command'     => 'gcode_line',
                'param'       => $gcode,
                'user_id'     => '0',
            ],
        ]);
    }

    // Home
    public function homeAll(): bool
    {
        return $this->sendGcode("G28\n");
    }

    public function homeAxis(string $axis): bool
    {
        return $this->sendGcode("G28 {$axis}\n");
    }

    // Pohyb os (relativní)
    public function moveAxis(string $axis, float $distance, int $speed = 3000): bool
    {
        return $this->sendGcode("G91\nG1 {$axis}{$distance} F{$speed}\nG90\n");
    }

}
