<?php

use App\Models\File;
use App\Models\Printer;
use App\Models\PrintJob;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/files', function () {
        return view('files');
    })->name('files');

    Route::get('/files/{file}', function (\App\Models\File $file) {
        if ($file->user_id !== auth()->id()) abort(403);
        return view('file-detail-page', compact('file'));
    })->name('file.show');

    Route::get('/files/{id}/download', function ($id) {
        $file = App\Models\File::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();
        $path = storage_path('app/private/' . $file->disk_path);
        return response()->download($path, $file->original_name);
    })->name('file.download');

    Route::get('/printers', function () {
        return view('printers-overview');
    })->name('printers');

    Route::get('/history', function () {
        return view('history');
    })->name('history');

    // Snímek z kamery uložený na konci tisku
    Route::get('/history/{job}/snapshot', function (PrintJob $job) {
        if (!$job->snapshot_path || !Storage::disk('local')->exists($job->snapshot_path)) {
            abort(404);
        }
        return response(Storage::disk('local')->get($job->snapshot_path))
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=86400');
    })->name('history.snapshot');

    Route::get('/settings/printers', function () {
        return view('printers');
    })->name('printers.manage');

    Route::get('/settings/modules', function () {
        return view('modules');
    })->name('modules.settings');

    Route::get('/settings/versions', function () {
        return view('versions');
    })->name('versions.settings');

    // Stažení zálohy databáze (název se ověřuje v BackupService)
    Route::get('/settings/versions/backups/{name}', function (string $name) {
        $path = (new \App\Services\BackupService())->pathFor($name);
        abort_unless($path, 404);
        return response()->download($path, $name);
    })->where('name', '[A-Za-z0-9._-]+')->name('versions.backup.download');

    Route::get('/settings/notifications', function () {
        return view('notifications');
    })->name('notifications.settings');

    Route::get('/printers/{printer}', function (Printer $printer) {
        return view('printer-detail', compact('printer'));
    })->name('printer.detail');

    Route::get('/printers/{printer}/snapshot', function (Printer $printer) {
        $response = Http::timeout(5)->get(
            'http://127.0.0.1:1984/api/frame.jpeg?src=printer_' . $printer->id
        );

        if ($response->successful()) {
            return response($response->body())
                ->header('Content-Type', 'image/jpeg')
                ->header('Cache-Control', 'no-store');
        }

        abort(404);
    })->name('printer.snapshot');

    Route::get('/printers/{printer}/stream', function (Printer $printer) {
        return response()->stream(function () use ($printer) {
            set_time_limit(0);
            ignore_user_abort(false);

            $url = 'rtsps://bblp:' . $printer->access_code . '@' . $printer->ip_address . ':322/streaming/live/1';
            $cmd = 'ffmpeg -rtsp_transport tcp -i ' . escapeshellarg($url) . ' -f mjpeg -r 10 -q:v 5 pipe:1 2>/dev/null';

            $pipe = popen($cmd, 'r');
            if (!$pipe) return;

            $buffer = '';

            while (!feof($pipe) && !connection_aborted()) {
                $chunk = fread($pipe, 65536);
                if ($chunk === false) break;
                $buffer .= $chunk;

                while (true) {
                    $start = strpos($buffer, "\xFF\xD8");
                    $end   = strpos($buffer, "\xFF\xD9");

                    if ($start !== false && $end !== false && $end > $start) {
                        $jpeg   = substr($buffer, $start, $end - $start + 2);
                        $buffer = substr($buffer, $end + 2);

                        echo "--frame\r\n";
                        echo "Content-Type: image/jpeg\r\n";
                        echo "Content-Length: " . strlen($jpeg) . "\r\n\r\n";
                        echo $jpeg;
                        echo "\r\n";

                        if (ob_get_level() > 0) ob_flush();
                        flush();
                    } else {
                        break;
                    }
                }
            }

            pclose($pipe);
        }, 200, [
            'Content-Type'      => 'multipart/x-mixed-replace; boundary=frame',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    })->name('printer.stream');

    Route::get('/modules', function () {
        return view('modules');
    })->name('modules');

    Route::get('/file/thumbnail/{id}', function (int $id) {
        $file = File::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if (!$file->thumbnail_path || !Storage::disk('local')->exists($file->thumbnail_path)) {
            abort(404);
        }

        return response(Storage::disk('local')->get($file->thumbnail_path))
            ->header('Content-Type', 'image/png');
    })->name('file.thumbnail');

    Route::get('/file/{id}/plate/{plateIndex}/thumbnail', function (int $id, int $plateIndex) {
        $file = File::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $plates = $file->metadata['plates'] ?? [];
        $plate  = collect($plates)->firstWhere('index', $plateIndex);

        if (!$plate || empty($plate['thumbnail_path']) || !Storage::disk('local')->exists($plate['thumbnail_path'])) {
            abort(404);
        }

        return response(Storage::disk('local')->get($plate['thumbnail_path']))
            ->header('Content-Type', 'image/png');
    })->name('file.plate.thumbnail');


});
