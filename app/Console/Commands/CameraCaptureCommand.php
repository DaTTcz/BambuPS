<?php

namespace App\Console\Commands;

use App\Models\Printer;
use Illuminate\Console\Command;

class CameraCaptureCommand extends Command
{
    protected $signature   = 'camera:capture {printer_id}';
    protected $description = 'HLS stream + snapshot pro tiskárnu';

    public function handle(): int
    {
        $printerId = $this->argument('printer_id');

        while (true) {
            $printer = Printer::find($printerId);

            if (!$printer || !$printer->enabled) {
                $this->warn('Tiskárna nenalezena. Čekám 30s...');
                sleep(30);
                continue;
            }

            $rtspUrl  = 'rtsps://bblp:' . $printer->access_code . '@' . $printer->ip_address . ':322/streaming/live/1';
            $camDir   = storage_path('app/cameras/' . $printer->id);
            $m3u8     = $camDir . '/stream.m3u8';
            $snapshot = $camDir . '/snapshot.jpg';

            if (!is_dir($camDir)) {
                mkdir($camDir, 0775, true);
            }

            // Uklidit staré segmenty před spuštěním
            $oldSegments = glob($camDir . DIRECTORY_SEPARATOR . '*.ts');
            if ($oldSegments) {
                foreach ($oldSegments as $tsFile) {
                    @unlink($tsFile);
                }
            }
            if (file_exists($m3u8)) {
                @unlink($m3u8);
            }

            $this->info('[' . now()->format('H:i:s') . '] Spouštím stream pro: ' . $printer->name);

            $cmd = 'ffmpeg'
                . ' -rtsp_transport tcp'
                . ' -i ' . escapeshellarg($rtspUrl)
                . ' -c:v copy'
                . ' -hls_time 1'
                . ' -hls_list_size 3'
                . ' -hls_flags delete_segments+omit_endlist'
                . ' -f hls ' . escapeshellarg($m3u8)
                . ' -vf fps=1/5 -update 1 -y ' . escapeshellarg($snapshot)
                . ' 2>&1';

            passthru($cmd, $exitCode);

            $this->warn('[' . now()->format('H:i:s') . '] ffmpeg skončil (kód: ' . $exitCode . '). Čistím a restartuji za 15s...');

            // Uklidit po sobě
            $oldSegments = glob($camDir . DIRECTORY_SEPARATOR . '*.ts');
            if ($oldSegments) {
                foreach ($oldSegments as $tsFile) {
                    @unlink($tsFile);
                }
            }
            if (file_exists($m3u8)) {
                @unlink($m3u8);
            }

            sleep(15);
        }
    }
}
