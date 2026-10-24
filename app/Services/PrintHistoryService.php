<?php

namespace App\Services;

use App\Models\File;
use App\Models\Module;
use App\Models\PrintJob;
use App\Models\Printer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Historie tisku.
 *
 * - queue(): volá se z appky hned po odeslání tisku (FileDetailPage) - vytvoří
 *   záznam ve stavu "queued" s vazbou na soubor, desku a task_id.
 * - sync():  volá MQTT listener u každé zprávy. Podle gcode_state/task_id
 *   tisk otevře (navázáním na "queued" záznam, nebo jako externí tisk ze
 *   Studia/displeje) a při konci ho uzavře (výsledek, doba, snímek z kamery).
 *
 * Databázi sync() řeší jen při změně stavu nebo task_id (případně při první
 * zprávě po startu listeneru), ne u každé z mnoha průběžných zpráv.
 */
class PrintHistoryService
{
    /** Bambu: "Print task was cancelled" (0x0300400C) - FAILED kvůli zrušení uživatelem */
    private const ERROR_CANCELLED = 50348044;

    /** Poslední zpracovaný [stav, task_id] na tiskárnu - drží se v paměti listeneru */
    private static array $lastSeen = [];

    public static function queue(Printer $printer, File $file, int $plateIndex, ?string $taskId, ?int $userId, ?string $bedType = null, ?int $bedTemp = null): PrintJob
    {
        $plates = $file->metadata['plates'] ?? [];
        $plate  = collect($plates)->firstWhere('index', $plateIndex) ?? [];

        $filaments = array_map(fn ($f) => [
            'type'   => $f['type'] ?? '?',
            'color'  => $f['color'] ?? '#888888',
            'used_g' => isset($f['used_g']) ? round((float) $f['used_g'], 2) : null,
        ], $plate['filaments'] ?? []);

        $filamentG = $plate['filament_used_g']
            ?? (count($plates) <= 1 ? ($file->metadata['filament_used_g'] ?? null) : null);

        $estimated = $plate['print_time_seconds']
            ?? (count($plates) <= 1 ? ($file->metadata['print_time_seconds'] ?? null) : null);

        $data = [
            'file_id'           => $file->id,
            'user_id'           => $userId,
            'name'              => pathinfo($file->original_name, PATHINFO_FILENAME),
            'plate_index'       => $plateIndex,
            'bed_type'          => $bedType,
            'bed_temp'          => $bedTemp,
            'source'            => 'bambups',
            'estimated_seconds' => $estimated ? (int) $estimated : null,
            'total_layers'      => $plate['total_layer_num'] ?? null,
            'filament_g'        => $filamentG !== null ? round((float) $filamentG, 2) : null,
            'filaments'         => $filaments ?: null,
        ];

        // Listener mohl tisk zachytit dřív, než se sem dostaneme (tiskárna
        // odpoví během milisekund) - pak ho jen doplníme o údaje ze souboru.
        if ($taskId) {
            $already = PrintJob::where('printer_id', $printer->id)
                ->where('status', PrintJob::STATUS_RUNNING)
                ->where('task_id', $taskId)
                ->latest('id')
                ->first();
            if ($already) {
                $already->update($data);
                return $already;
            }
        }

        // Starší neodstartované záznamy pro tuhle tiskárnu už nemají smysl
        PrintJob::where('printer_id', $printer->id)
            ->where('status', PrintJob::STATUS_QUEUED)
            ->delete();

        return PrintJob::create([
            'printer_id'        => $printer->id,
            'file_id'           => $file->id,
            'user_id'           => $userId,
            'printer_name'      => $printer->name,
            'name'              => pathinfo($file->original_name, PATHINFO_FILENAME),
            'plate_index'       => $plateIndex,
            'source'            => 'bambups',
            'task_id'           => $taskId,
            'status'            => PrintJob::STATUS_QUEUED,
            'estimated_seconds' => $estimated ? (int) $estimated : null,
            'total_layers'      => $plate['total_layer_num'] ?? null,
            'filament_g'        => $filamentG !== null ? round((float) $filamentG, 2) : null,
            'filaments'         => $filaments ?: null,
        ]);
    }

    public function sync(Printer $printer, array $prev, array $status): void
    {
        $state  = (string) ($status['gcode_state'] ?? '');
        $taskId = (string) ($status['task_id'] ?? '');

        if ($state === '') {
            return;
        }

        $key = $state . '|' . $taskId;
        if ((self::$lastSeen[$printer->id] ?? null) === $key) {
            return;
        }
        self::$lastSeen[$printer->id] = $key;

        $open = PrintJob::where('printer_id', $printer->id)
            ->where('status', PrintJob::STATUS_RUNNING)
            ->latest('id')
            ->first();

        if (in_array($state, PrintJob::ACTIVE_STATES, true)) {
            $queued = $this->findQueued($printer, $taskId);

            if ($open) {
                if ($taskId === '' || $open->task_id === $taskId) {
                    return; // pořád tentýž tisk (např. RUNNING -> PAUSE -> RUNNING)
                }

                // Ve fázi PREPARE tiskárna chvíli hlásí ještě task_id předchozí
                // úlohy - záznam založený v posledních minutách je tentýž tisk,
                // jen teď dostal správné task_id.
                if ($open->started_at && $open->started_at->gt(now()->subMinutes(3))) {
                    if ($queued) {
                        $open->delete();
                        $this->promote($queued, $status);
                    } else {
                        $open->update([
                            'task_id' => $taskId,
                            'name'    => $open->source === 'external'
                                ? ($status['subtask_name'] ?? $open->name)
                                : $open->name,
                        ]);
                    }
                    return;
                }

                // Začal jiný tisk, aniž bychom viděli konec předchozího
                $this->close($open, PrintJob::STATUS_CANCELLED, $prev, $status, false);
            }

            $queued ? $this->promote($queued, $status) : $this->createExternal($printer, $taskId, $status);
            $this->cleanupQueued($printer);
            return;
        }

        if ($open) {
            $result = match ($state) {
                'FINISH' => PrintJob::STATUS_FINISHED,
                'FAILED' => ((int) ($status['print_error'] ?? 0) === self::ERROR_CANCELLED)
                                ? PrintJob::STATUS_CANCELLED
                                : PrintJob::STATUS_FAILED,
                default  => PrintJob::STATUS_CANCELLED, // IDLE apod. po běžícím tisku
            };
            $this->close($open, $result, $prev, $status, true);
        }
    }

    private function findQueued(Printer $printer, string $taskId): ?PrintJob
    {
        if ($taskId === '') {
            return null;
        }

        return PrintJob::where('printer_id', $printer->id)
            ->where('status', PrintJob::STATUS_QUEUED)
            ->where('task_id', $taskId)
            ->latest('id')
            ->first();
    }

    /** Tisk odeslaný z appky se opravdu rozběhl */
    private function promote(PrintJob $job, array $status): void
    {
        $remainingMin = $status['mc_remaining_time'] ?? null;
        $percent      = (int) ($status['mc_percent'] ?? 0);

        $job->update([
            'status'            => PrintJob::STATUS_RUNNING,
            'started_at'        => now(),
            'estimated_seconds' => $job->estimated_seconds
                ?? ($remainingMin && $percent <= 1 ? (int) $remainingMin * 60 : null),
            'total_layers'      => $job->total_layers ?? ($status['total_layer_num'] ?? null),
        ]);
    }

    /** Tisk spuštěný mimo appku (Bambu Studio, OrcaSlicer, displej, Handy) */
    private function createExternal(Printer $printer, string $taskId, array $status): void
    {
        $remainingMin = $status['mc_remaining_time'] ?? null;
        $percent      = (int) ($status['mc_percent'] ?? 0);

        // Soubor stejného jména v appce (např. nahraný přes slicer connector)
        $name = $status['subtask_name'] ?? 'Neznámý tisk';
        $file = File::whereIn('original_name', [$name . '.gcode.3mf', $name . '.3mf', $name . '.gcode', $name])
            ->latest('id')->first();

        PrintJob::create([
            'printer_id'        => $printer->id,
            'file_id'           => $file?->id,
            'printer_name'      => $printer->name,
            'name'              => $name,
            'source'            => 'external',
            'task_id'           => $taskId !== '' ? $taskId : null,
            'status'            => PrintJob::STATUS_RUNNING,
            'started_at'        => now(),
            'estimated_seconds' => ($remainingMin && $percent <= 1) ? (int) $remainingMin * 60 : null,
            'total_layers'      => $status['total_layer_num'] ?? null,
        ]);
    }

    /** "queued" záznamy, které se do 30 min nerozběhly (tisk z appky selhal při startu) */
    private function cleanupQueued(Printer $printer): void
    {
        PrintJob::where('printer_id', $printer->id)
            ->where('status', PrintJob::STATUS_QUEUED)
            ->where('created_at', '<', now()->subMinutes(30))
            ->delete();
    }

    private function close(PrintJob $job, string $result, array $prev, array $status, bool $takeSnapshot): void
    {
        // Po přechodu do IDLE se průběh často vynuluje - bereme vyšší z hodnot
        $progress = max((int) ($prev['mc_percent'] ?? 0), (int) ($status['mc_percent'] ?? 0));
        $layer    = max((int) ($prev['layer_num'] ?? 0), (int) ($status['layer_num'] ?? 0));
        if ($result === PrintJob::STATUS_FINISHED) {
            $progress = 100;
        }

        $hms = $status['hms'] ?? ($prev['hms'] ?? []);
        $err = (int) ($status['print_error'] ?? 0);

        $job->update([
            'status'           => $result,
            'ended_at'         => now(),
            'duration_seconds' => $job->started_at ? (int) $job->started_at->diffInSeconds(now()) : null,
            'progress'         => min($progress, 100),
            'layer_reached'    => $layer ?: null,
            'total_layers'     => $job->total_layers ?? ($status['total_layer_num'] ?? null),
            'print_error'      => $err > 0 ? $err : null,
            'hms'              => !empty($hms) ? $hms : null,
        ]);

        if ($takeSnapshot) {
            $this->saveSnapshot($job);
        }
    }

    /** Snímek z kamery na konci tisku (jen když běží go2rtc) */
    private function saveSnapshot(PrintJob $job): void
    {
        if (!$job->printer_id || !Module::isEnabled('go2rtc')) {
            return;
        }

        try {
            $response = Http::timeout(5)->get('http://127.0.0.1:1984/api/frame.jpeg?src=printer_' . $job->printer_id);
            if ($response->successful() && strlen($response->body()) > 1000) {
                $path = 'print-history/' . $job->id . '.jpg';
                Storage::disk('local')->put($path, $response->body());
                $job->update(['snapshot_path' => $path]);
            }
        } catch (\Throwable $e) {
            Log::warning('Historie tisku: snímek z kamery se nepodařilo uložit: ' . $e->getMessage());
        }
    }
}
