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
        'enabled',
        'status',
        'pending_ams_kick',
        'last_seen_at',
    ];

    protected $casts = [
        'enabled'          => 'boolean',
        'status'           => 'array',
        'pending_ams_kick' => 'array',
        'last_seen_at'     => 'datetime',
    ];

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
