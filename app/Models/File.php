<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class File extends Model
{
    protected $fillable = [
        'folder_id',
        'user_id',
        'original_name',
        'stored_name',
        'disk_path',
        'size_bytes',
        'metadata',
        'thumbnail_path',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getSizeFormattedAttribute(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }

    /**
     * Náhled pro přehled souborů (kartička/seznam). Preferuje náhled
     * KONKRÉTNÍ desky (stejný, co se zobrazuje v detailu) před obecným
     * "projektovým" náhledem z .3mf - ten může patřit jiné desce, než
     * se kterou soubor skutečně tiskne (např. když měl projekt ve
     * slicéru víc desek).
     */
    public function getListThumbnailUrlAttribute(): ?string
    {
        $plates = $this->metadata['plates'] ?? [];
        if (!empty($plates[0]['thumbnail_path']) && !empty($plates[0]['index'])) {
            return route('file.plate.thumbnail', ['id' => $this->id, 'plateIndex' => $plates[0]['index']]);
        }
        if ($this->thumbnail_path) {
            return route('file.thumbnail', $this->id);
        }
        return null;
    }
}
