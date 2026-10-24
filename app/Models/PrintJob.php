<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    public const STATUS_QUEUED    = 'queued';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_FINISHED  = 'finished';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /** Stavy tiskárny (gcode_state), kdy tisk probíhá */
    public const ACTIVE_STATES = ['PREPARE', 'SLICING', 'RUNNING', 'PAUSE'];

    protected $fillable = [
        'printer_id', 'file_id', 'user_id',
        'printer_name', 'name', 'plate_index',
        'bed_type', 'bed_temp', 'bed_leveled',
        'source', 'task_id', 'status',
        'started_at', 'ended_at', 'duration_seconds', 'estimated_seconds',
        'progress', 'layer_reached', 'total_layers',
        'filament_g', 'filaments',
        'print_error', 'hms', 'snapshot_path',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
        'filaments'  => 'array',
        'hms'        => 'array',
        'filament_g' => 'float',
        'bed_leveled' => 'boolean',
    ];

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    /**
     * Skutečně spotřebovaný filament - u dokončeného tisku odhad slicéru,
     * u nedokončeného odhad přepočtený podle dosaženého průběhu.
     */
    public function getFilamentUsedGAttribute(): ?float
    {
        if ($this->filament_g === null) return null;
        if ($this->status === self::STATUS_FINISHED) return $this->filament_g;
        if ($this->progress === null) return null;
        return round($this->filament_g * $this->progress / 100, 1);
    }

    /** Délka tisku - u běžícího počítaná průběžně */
    public function getDurationAttribute(): ?int
    {
        if ($this->duration_seconds !== null) return $this->duration_seconds;
        if ($this->started_at && $this->status === self::STATUS_RUNNING) {
            return (int) $this->started_at->diffInSeconds(now());
        }
        return null;
    }

    public static function formatSeconds(?int $seconds): string
    {
        if ($seconds === null) return '–';
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        if ($h > 0) return "{$h}h " . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . 'm';
        return max($m, 0) . 'm';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_QUEUED    => 'Odesláno',
            self::STATUS_RUNNING   => 'Tiskne se',
            self::STATUS_FINISHED  => 'Dokončeno',
            self::STATUS_FAILED    => 'Selhalo',
            self::STATUS_CANCELLED => 'Zrušeno',
            default                => $this->status,
        };
    }

    /** Náhled: snímek z kamery na konci, jinak náhled desky souboru */
    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->snapshot_path) {
            return route('history.snapshot', $this->id);
        }
        if ($this->file) {
            $plates = $this->file->metadata['plates'] ?? [];
            $plate  = collect($plates)->firstWhere('index', $this->plate_index);
            if ($plate && !empty($plate['thumbnail_path'])) {
                return route('file.plate.thumbnail', ['id' => $this->file->id, 'plateIndex' => $plate['index']]);
            }
            return $this->file->list_thumbnail_url;
        }
        return null;
    }
}
