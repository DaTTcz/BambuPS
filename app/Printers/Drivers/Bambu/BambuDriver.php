<?php

namespace App\Printers\Drivers\Bambu;

use App\Printers\AbstractPrinterDriver;
use App\Printers\Capability;
use App\Printers\PrintRequest;
use App\Services\BambuFtpService;
use App\Services\BambuMqttService;
use App\Services\PrinterCommandService;

/**
 * Bambu Lab v LAN režimu: stav a příkazy přes MQTT (port 8883),
 * soubory přes FTPS (port 990), kamera přes RTSPS (port 322).
 */
class BambuDriver extends AbstractPrinterDriver
{
    public static function key(): string
    {
        return 'bambu';
    }

    public static function label(): string
    {
        return 'Bambu Lab (LAN)';
    }

    public static function description(): string
    {
        return 'X1, P1, A1 a další tiskárny Bambu Lab s povoleným LAN režimem.';
    }

    public static function connectionFields(): array
    {
        return [
            'ip_address'    => ['label' => 'IP adresa', 'type' => 'text', 'rules' => 'required|ip', 'placeholder' => '192.168.1.50'],
            'serial_number' => ['label' => 'Sériové číslo', 'type' => 'text', 'rules' => 'required|string|max:64',
                                'help' => 'Na displeji tiskárny v Nastavení → Zařízení'],
            'access_code'   => ['label' => 'Přístupový kód (LAN)', 'type' => 'text', 'rules' => 'required|string|max:32', 'secret' => true,
                                'help' => 'Na displeji tiskárny v Nastavení → Síť (LAN Only)'],
        ];
    }

    public static function capabilities(): array
    {
        return [
            Capability::PRINT_CONTROL,
            Capability::TEMPERATURE,
            Capability::FANS,
            Capability::SPEED,
            Capability::MOTION,
            Capability::GCODE,
            Capability::LIGHT,
            Capability::CAMERA,
            Capability::CAMERA_RESOLUTION,
            Capability::MATERIAL_SYSTEM,
            Capability::MULTI_PLATE,
            Capability::BED_LEVELING,
            Capability::FLOW_CALIBRATION,
            Capability::VIBRATION_CALIBRATION,
            Capability::FIRST_LAYER_INSPECTION,
            Capability::TIMELAPSE,
            Capability::ERROR_CODES,
            Capability::SKIP_OBJECTS,
        ];
    }

    public static function fileTypes(): array
    {
        return ['3mf'];
    }

    private function cmd(): PrinterCommandService
    {
        return new PrinterCommandService($this->printer);
    }

    public function testConnection(): array
    {
        $mqtt = new BambuMqttService($this->printer);
        if (!$mqtt->connect()) {
            return ['ok' => false, 'message' => 'K tiskárně se nepodařilo připojit (MQTT 8883). Zkontroluj IP adresu, přístupový kód a LAN režim.'];
        }
        $mqtt->disconnect();
        return ['ok' => true, 'message' => 'Připojení k tiskárně funguje.'];
    }

    // ---- Soubory a tisk -------------------------------------------------

    public function uploadFile(string $localPath, string $remoteName): bool
    {
        $ftp = new BambuFtpService($this->printer);
        // Smazat starý soubor se stejným jménem - přepis přes STOR nemusí
        // vyčistit interní stav tiskárny vázaný na předchozí (např. neúspěšný) tisk.
        $ftp->delete($remoteName);
        return $ftp->upload($localPath, $remoteName);
    }

    public function startPrint(PrintRequest $request): ?string
    {
        // Mapování filamentů do tvaru, který očekává Bambu (ams/slot/tray_id)
        $amsMapping = array_map(fn ($m) => [
            'ams'            => (int) $m['unit'],
            'slot'           => (int) $m['slot'],
            'tray_id'        => (int) $m['unit'] * 4 + (int) $m['slot'],
            'extruder_index' => (int) ($m['extruder_index'] ?? 0),
        ], array_values($request->materials));

        $cmd = $this->cmd();
        $ok  = $cmd->startPrint(
            $request->remoteName,
            $request->plateIndex,
            (bool) $request->option(PrintRequest::OPT_USE_MATERIAL_SYSTEM, true),
            (string) $request->option(PrintRequest::OPT_BED_TYPE, 'auto'),
            $amsMapping,
            (bool) $request->option(PrintRequest::OPT_BED_LEVELING, true),
            (bool) $request->option(PrintRequest::OPT_TIMELAPSE, false),
            (bool) $request->option(PrintRequest::OPT_FIRST_LAYER_INSPECT, false),
            (bool) $request->option(PrintRequest::OPT_FLOW_CALIBRATION, false),
            (bool) $request->option(PrintRequest::OPT_VIBRATION_CALIBRATION, false),
        );

        return $ok ? ($cmd->lastTaskId ?? '') : null;
    }

    /**
     * Bambu: příkaz skip_objects se seznamem identify_id (stejná čísla jako
     * "unique label id" v gcode). Tiskárna vynechané hlásí v print.s_obj.
     * Funguje u souborů slicovaných se zapnutým "Label objects".
     */
    public function skipObjects(array $objectIds): bool
    {
        $ids = array_values(array_unique(array_map('intval', $objectIds)));
        if (!$ids) {
            return false;
        }
        return $this->cmd()->send([
            'print' => [
                'sequence_id' => (string) time(),
                'command'     => 'skip_objects',
                'obj_list'    => $ids,
            ],
        ]);
    }

    public function pause(): bool  { return $this->cmd()->pause(); }
    public function resume(): bool { return $this->cmd()->resume(); }
    public function stop(): bool   { return $this->cmd()->stop(); }

    // ---- Ovládání -------------------------------------------------------

    public function setHotendTemp(int $temp, int $tool = 0): bool { return $this->cmd()->setHotendTemp($temp); }
    public function setBedTemp(int $temp): bool { return $this->cmd()->setBedTemp($temp); }
    /** Bambu: 1 = chlazení dílu, 2 = pomocný, 3 = komora */
    public function setFan(int $fan, int $percent): bool { return $this->cmd()->setFan($fan, $percent); }
    public function setPrintSpeed(int $percent): bool { return $this->cmd()->setPrintSpeed($percent); }
    public function setLight(string $node, string $mode): bool { return $this->cmd()->setLight($node, $mode); }
    public function homeAll(): bool { return $this->cmd()->homeAll(); }
    public function homeAxis(string $axis): bool { return $this->cmd()->homeAxis($axis); }
    public function moveAxis(string $axis, float $distance, int $speed = 3000): bool { return $this->cmd()->moveAxis($axis, $distance, $speed); }
    public function sendGcode(string $gcode): bool
    {
        return $this->cmd()->send([
            'print' => ['sequence_id' => '0', 'command' => 'gcode_line', 'param' => rtrim($gcode) . "\n", 'user_id' => '0'],
        ]);
    }

    /** Bambu navíc: rozlišení kamery X1 ('720p' / '1080p') */
    public function setCameraResolution(string $resolution): bool
    {
        return $this->cmd()->setCameraResolution($resolution);
    }

    // ---- Kamera ---------------------------------------------------------

    public function cameraSource(): ?string
    {
        $ip   = $this->conn('ip_address');
        $code = $this->conn('access_code');
        return $ip && $code ? "rtsps://bblp:{$code}@{$ip}:322/streaming/live/1" : null;
    }
}
