<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    protected $fillable = [
        'name',
        'model',
        'serial_number',
        'ip_address',
        'access_code',
        'camera_stream_url',
        'camera_snapshot_url',
        'enabled',
        'status',
        'pending_ams_kick',
        'bed_mesh',
        'last_seen_at',
    ];

    protected $casts = [
        'enabled'          => 'boolean',
        'status'           => 'array',
        'pending_ams_kick' => 'array',
        'bed_mesh'         => 'array',
        'last_seen_at'     => 'datetime',
    ];

    /** Má tiskárna nastavenou vlastní (externí) kameru? */
    public function hasCustomCamera(): bool
    {
        return trim((string) $this->camera_stream_url) !== '';
    }

    /** Zdroj streamu pro go2rtc - vlastní kamera, jinak vestavěná Bambu kamera */
    public function cameraSource(): string
    {
        return $this->hasCustomCamera()
            ? trim($this->camera_stream_url)
            : "rtsps://bblp:{$this->access_code}@{$this->ip_address}:322/streaming/live/1";
    }

    /**
     * Aktuální snímek z kamery (JPEG), nebo null. Přednost má vlastní URL
     * snímku (rychlejší, plné rozlišení), jinak snímek ze streamu přes go2rtc.
     */
    public function fetchSnapshot(int $timeout = 5): ?string
    {
        $urls = array_filter([
            trim((string) $this->camera_snapshot_url) ?: null,
            Module::isEnabled('go2rtc') ? 'http://127.0.0.1:1984/api/frame.jpeg?src=printer_' . $this->id : null,
        ]);

        foreach ($urls as $url) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout($timeout)->get($url);
                // Jen skutečný JPEG (FF D8) - ne chybová stránka ani MJPEG stream
                if ($response->successful() && strlen($response->body()) > 1000 && str_starts_with($response->body(), "\xFF\xD8")) {
                    return $response->body();
                }
            } catch (\Throwable $e) {
                // zkusit další zdroj
            }
        }

        return null;
    }

    /** print.stg_cur - fáze, kdy je tisk zastavený kvůli konci filamentu */
    public const STAGE_FILAMENT_RUNOUT = 6;

    /** Fáze tisku Bambu (print.stg_cur) */
    public const STAGES = [
        1  => '📐 Vyrovnávání podložky',
        2  => '🔥 Ohřev podložky',
        3  => '🔧 Kalibrace os',
        4  => '🔄 Výměna filamentu',
        5  => '⏸ Pauza (M400)',
        6  => '🧵 Pauza – došel filament',
        7  => '🔥 Ohřev trysky',
        8  => '🔧 Kalibrace extruze',
        9  => '🔍 Skenování podložky',
        10 => '🔍 Kontrola první vrstvy',
        11 => '🔍 Rozpoznávání podložky',
        12 => '🔧 Kalibrace lidaru',
        13 => '🏠 Najíždění na nulu',
        14 => '🧹 Čištění trysky',
        15 => '🌡️ Kontrola teploty extruderu',
        16 => '⏸ Pozastaveno uživatelem',
        17 => '⏸ Pauza – spadl přední kryt',
        18 => '🔧 Kalibrace lidaru',
        19 => '🔧 Kalibrace průtoku',
        20 => '⚠️ Pauza – chyba teploty trysky',
        21 => '⚠️ Pauza – chyba teploty podložky',
        22 => '🔄 Vysouvání filamentu',
        23 => '⏸ Pauza – přeskočení kroku',
        24 => '🔄 Zavádění filamentu',
        25 => '🔧 Kalibrace hluku motorů',
        26 => '⚠️ Pauza – ztráta spojení s AMS',
        27 => '⚠️ Pauza – nízké otáčky ventilátoru',
        28 => '⚠️ Pauza – chyba teploty komory',
        29 => '❄️ Chlazení komory',
        30 => '⏸ Pauza – G-code uživatele',
        32 => '⚠️ Pauza – zakrytá tryska',
        33 => '⚠️ Pauza – chyba odstřihu filamentu',
        34 => '⚠️ Pauza – chyba první vrstvy',
        35 => '⚠️ Pauza – ucpaná tryska',
    ];

    /**
     * Co tiskárna právě dělá v rámci tisku (výměna filamentu, ohřev, ...).
     * Null, když netiskne nebo prostě tiskne (fáze 0).
     */
    public function getStageLabelAttribute(): ?string
    {
        if (!in_array($this->status['gcode_state'] ?? '', ['RUNNING', 'PAUSE', 'PREPARE'], true)) {
            return null;
        }
        return self::STAGES[(int) ($this->status['stg_cur'] ?? -1)] ?? null;
    }

    public function getStatusTextAttribute(): string
    {
        if (!Module::isEnabled('mqtt_connector')) {
            return 'MQTT vypnut';
        }
        return $this->status['gcode_state'] ?? 'Neznámý';
    }

    public function getIsOnlineAttribute(): bool
    {
        if (!Module::isEnabled('mqtt_connector')) {
            return false;
        }
        if (!$this->last_seen_at) return false;
        return $this->last_seen_at->diffInMinutes(now()) < 5;
    }

    public function getNozzleTempAttribute(): ?float
    {
        return $this->status['temperatures']['nozzle_temper'] ?? null;
    }

    public function getBedTempAttribute(): ?float
    {
        return $this->status['temperatures']['bed_temper'] ?? null;
    }

    public function getPrintProgressAttribute(): ?int
    {
        return $this->status['mc_percent'] ?? null;
    }

    public function getPrintFileAttribute(): ?string
    {
        return $this->status['subtask_name'] ?? null;
    }
}
