<?php

namespace App\Services;

use App\Models\PrintJob;
use App\Models\Printer;
use Carbon\Carbon;

/**
 * Chytré vyrovnání podložky.
 *
 * Tiskárna (ověřeno na X1C) si pamatuje mesh z posledního měření a při
 * vypnutém "bed_leveling" ho použije znovu. Měřit tedy stačí, když se
 * podmínky změnily. Stav posledního měření drží printers.bed_mesh:
 *
 *  - markMeasured()  - MQTT listener viděl fázi tisku stg_cur = 1 (Auto bed leveling)
 *  - invalidate()    - tiskárna byla offline (pravděpodobně vypnutá) apod.
 *  - advise()        - rozhodnutí pro dialog tisku: měřit / neměřit + důvod
 */
class BedMeshService
{
    /** Fáze tisku Bambu "Auto bed leveling" (print.stg_cur) */
    public const STAGE_BED_LEVELING = 1;

    /** Za jak dlouhý výpadek spojení bereme tiskárnu jako vypnutou (s) */
    public const OFFLINE_INVALIDATE_SECONDS = 90;

    public function markMeasured(Printer $printer, ?string $bedType, ?int $bedTemp): void
    {
        $printer->update(['bed_mesh' => [
            'valid'       => true,
            'measured_at' => now()->toIso8601String(),
            'bed_type'    => $bedType,
            'bed_temp'    => $bedTemp,
            'reason'      => null,
        ]]);
    }

    public function invalidate(Printer $printer, string $reason): void
    {
        $mesh = $printer->bed_mesh ?? [];
        if (($mesh['valid'] ?? false) === false && ($mesh['reason'] ?? null) === $reason) {
            return;
        }
        $mesh['valid']  = false;
        $mesh['reason'] = $reason;
        $printer->update(['bed_mesh' => $mesh]);
    }

    /**
     * Volá MQTT listener u každé zprávy. Při fázi "Auto bed leveling"
     * zapíše měření (jednou za tisk) a označí běžící tisk v historii.
     */
    public function trackMessage(Printer $printer, array $print, array $status): void
    {
        if ((int) ($print['stg_cur'] ?? -1) !== self::STAGE_BED_LEVELING) {
            return;
        }

        $job = PrintJob::where('printer_id', $printer->id)
            ->where('status', PrintJob::STATUS_RUNNING)
            ->latest('id')
            ->first();

        // Jen jednou za tisk - fáze trvá desítky zpráv
        if ($job && $job->bed_leveled) {
            return;
        }

        $bedTemp = $status['temperatures']['bed_target_temper'] ?? null;
        $bedTemp = $bedTemp !== null && $bedTemp > 0 ? (int) round($bedTemp) : ($job->bed_temp ?? null);

        $this->markMeasured($printer, $job?->bed_type, $bedTemp);
        $job?->update(['bed_leveled' => true]);
    }

    /**
     * Doporučení pro tisk.
     * @return array{level: bool, reason: string}
     */
    public function advise(Printer $printer, ?string $bedType, ?int $bedTemp): array
    {
        $mesh    = $printer->bed_mesh ?? [];
        $maxDays = (int) config('bambups.bed_mesh_max_age_days', 7);
        $tol     = (int) config('bambups.bed_mesh_temp_tolerance', 10);

        if (empty($mesh) || empty($mesh['measured_at'])) {
            return ['level' => true, 'reason' => 'Appka zatím nezná žádné měření podložky na téhle tiskárně.'];
        }
        if (!($mesh['valid'] ?? false)) {
            return ['level' => true, 'reason' => ($mesh['reason'] ?? 'Uložené měření už neplatí') . '.'];
        }

        $measuredAt = Carbon::parse($mesh['measured_at']);
        $age        = $measuredAt->diffForHumans(['parts' => 1]);
        $info       = trim(($mesh['bed_type'] ?? 'neznámá podložka') . (isset($mesh['bed_temp']) ? ", {$mesh['bed_temp']} °C" : ''));

        if ($measuredAt->lt(now()->subDays($maxDays))) {
            return ['level' => true, 'reason' => "Poslední měření je starší než {$maxDays} dní (bylo {$age})."];
        }
        if (empty($mesh['bed_type'])) {
            return ['level' => true, 'reason' => 'Poslední měření proběhlo při tisku mimo appku, typ podložky není známý.'];
        }
        if ($bedType && strcasecmp($mesh['bed_type'], $bedType) !== 0) {
            return ['level' => true, 'reason' => "Jiná podložka než při posledním měření ({$mesh['bed_type']})."];
        }
        if ($bedTemp !== null && isset($mesh['bed_temp']) && abs($bedTemp - (int) $mesh['bed_temp']) > $tol) {
            return ['level' => true, 'reason' => "Teplota podložky {$bedTemp} °C se liší o víc než {$tol} °C od měření ({$mesh['bed_temp']} °C)."];
        }

        return ['level' => false, 'reason' => "Použije se uložené měření ({$age}, {$info})."];
    }
}
