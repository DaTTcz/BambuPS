<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Znovu načte metadata souboru (náhledy, desky, teploty...) aktuálním parserem.
 */
class FileReparseService
{
    /** @return bool false = soubor na disku chybí */
    public function reparse(File $file): bool
    {
        $fullPath = Storage::disk('local')->path($file->disk_path);
        if (!file_exists($fullPath)) {
            return false;
        }

        $extension = strtolower(pathinfo($file->disk_path, PATHINFO_EXTENSION));
        $metadata  = $extension === '3mf'
            ? (new ThreeMfParserService())->parse($fullPath)
            : (new GcodeParserService())->parse($fullPath);

        if (!empty($metadata['thumbnail_data'])) {
            if ($file->thumbnail_path) {
                Storage::disk('local')->delete($file->thumbnail_path);
            }
            $thumbnailPath = 'thumbnails/' . Str::uuid() . '.png';
            Storage::disk('local')->put($thumbnailPath, base64_decode($metadata['thumbnail_data']));
            $file->thumbnail_path = $thumbnailPath;
        }
        unset($metadata['thumbnail_data']);

        if (!empty($metadata['plates'])) {
            foreach ($file->metadata['plates'] ?? [] as $oldPlate) {
                if (!empty($oldPlate['thumbnail_path'])) {
                    Storage::disk('local')->delete($oldPlate['thumbnail_path']);
                }
            }
            foreach ($metadata['plates'] as $i => $plate) {
                if (!empty($plate['thumbnail_data'])) {
                    $plateThumbPath = 'thumbnails/' . Str::uuid() . '.png';
                    Storage::disk('local')->put($plateThumbPath, base64_decode($plate['thumbnail_data']));
                    $metadata['plates'][$i]['thumbnail_path'] = $plateThumbPath;
                }
                unset($metadata['plates'][$i]['thumbnail_data']);
            }
        }

        $file->metadata = $metadata;
        $file->save();

        return true;
    }
}
