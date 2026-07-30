<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Module;
use App\Services\ThreeMfParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\GcodeParserService;

class SlicerController extends Controller
{
    public function upload(Request $request, ThreeMfParserService $parser): JsonResponse
    {
        if (!Module::isEnabled('slicer_connector')) {
            return response()->json(['error' => 'Slicer konektor není aktivován.'], 403);
        }

        $request->validate([
            'file' => 'required|file|max:512000',
        ]);

        $fileRecord = $this->storeFile($request->file('file'), $request->user()->id, $parser);

        return response()->json([
            'success' => true,
            'file'    => [
                'id'            => $fileRecord->id,
                'original_name' => $fileRecord->original_name,
                'size_bytes'    => $fileRecord->size_bytes,
                'metadata'      => $fileRecord->metadata,
            ],
        ], 201);
    }

    public function ping(): JsonResponse
    {
        if (!Module::isEnabled('slicer_connector')) {
            return response()->json(['error' => 'Slicer konektor není aktivován.'], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'BambuPS Slicer Connector online',
            'version' => '1.0.0',
        ]);
    }

    public function octoprintVersion(): JsonResponse
    {
        return response()->json([
            'api'    => '0.1',
            'server' => '1.10.0',
            'text'   => 'OctoPrint 1.10.0',
        ]);
    }

    public function octoprintPrinter(): JsonResponse
    {
        if (!Module::isEnabled('slicer_connector')) {
            return response()->json(['error' => 'Slicer konektor není aktivován.'], 403);
        }

        return response()->json([
            'state' => [
                'text'  => 'Operational',
                'flags' => [
                    'operational'   => true,
                    'paused'        => false,
                    'printing'      => false,
                    'cancelling'    => false,
                    'pausing'       => false,
                    'error'         => false,
                    'ready'         => true,
                    'closedOrError' => false,
                ],
            ],
            'temperature' => [],
        ]);
    }

    public function octoprintUpload(Request $request, ThreeMfParserService $parser): JsonResponse
    {
        if (!Module::isEnabled('slicer_connector')) {
            return response()->json(['error' => 'Slicer konektor není aktivován.'], 403);
        }

        $uploadedFile = $request->file('file');
        if (!$uploadedFile) {
            return response()->json(['error' => 'Žádný soubor nebyl odeslán.'], 422);
        }

        $fileRecord = $this->storeFile($uploadedFile, $request->user()->id, $parser);

        return response()->json([
            'files' => [
                'local' => [
                    'name'   => $fileRecord->original_name,
                    'origin' => 'local',
                    'refs'   => [
                        'resource' => url('/api/octoprint/files/local/' . $fileRecord->id),
                    ],
                ],
            ],
            'done' => true,
        ], 201);
    }

    private function storeFile($file, int $userId, ThreeMfParserService $parser): File
    {
        $originalName = $file->getClientOriginalName();
        $extension    = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $storedName   = Str::uuid() . '.' . $extension;
        $diskPath     = 'prints/' . $storedName;
        $fullPath     = Storage::disk('local')->path($diskPath);

        @mkdir(dirname($fullPath), 0755, true);
        $file->move(dirname($fullPath), $storedName);

        if (!file_exists($fullPath)) {
            throw new \RuntimeException('Soubor se nepodařilo uložit: ' . $originalName);
        }

        // Vybereme správný parser podle přípony
        if (in_array($extension, ['3mf'])) {
            $metadata = $parser->parse($fullPath);
        } else {
            $gcodeParser = new GcodeParserService();
            $metadata    = $gcodeParser->parse($fullPath);
        }

        // Hlavní thumbnail
        $thumbnailPath = null;
        if (!empty($metadata['thumbnail_data'])) {
            $thumbName     = Str::uuid() . '.png';
            $thumbnailPath = 'thumbnails/' . $thumbName;
            Storage::disk('local')->put($thumbnailPath, base64_decode($metadata['thumbnail_data']));
            unset($metadata['thumbnail_data']);
        }

        // Plate thumbnaily – explicitní přiřazení
        if (!empty($metadata['plates'])) {
            $plates = $metadata['plates'];
            foreach ($plates as $i => $plate) {
                if (!empty($plate['thumbnail_data'])) {
                    $plateThumbPath = 'thumbnails/' . Str::uuid() . '.png';
                    Storage::disk('local')->put($plateThumbPath, base64_decode($plate['thumbnail_data']));
                    $plates[$i]['thumbnail_path'] = $plateThumbPath;
                }
                unset($plates[$i]['thumbnail_data']);
            }
            $metadata['plates'] = $plates;
        }

        return File::create([
            'folder_id'      => null,
            'user_id'        => $userId,
            'original_name'  => $originalName,
            'stored_name'    => $storedName,
            'disk_path'      => $diskPath,
            'size_bytes'     => filesize($fullPath),
            'metadata'       => $metadata,
            'thumbnail_path' => $thumbnailPath,
        ]);
    }
}
