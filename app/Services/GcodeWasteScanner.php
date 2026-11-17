<?php

namespace App\Services;

/**
 * Spočítá z G-code výměny filamentu a odpad u vícebarevného tisku:
 *  - proplach (Bambu/Orca: úseky mezi "; FLUSH_START" a "; FLUSH_END"),
 *  - čistící věž (extruze typu "Prime tower" / "Wipe tower").
 *
 * Volá se řádek po řádku (feed), výsledek v gramech podle hustoty a průměru
 * filamentu daného nástroje / slotu (result).
 *
 * Zároveň zjistí půdorys (bbox v XY) jednotlivých objektů podle značek
 * "; start printing object, unique label id: N" (Bambu/Orca) - pro mapu
 * objektů při vynechávání (objects()).
 */
class GcodeWasteScanner
{
    private int $changes = 0;
    private int $tool    = 0;
    private bool $inFlush = false;
    private bool $inTower = false;
    /** @var array<int, float> mm filamentu podle nástroje */
    private array $flushMm = [];
    private array $towerMm = [];

    private ?int $object = null;
    private ?float $x = null;
    private ?float $y = null;
    /** @var array<int, array{0: float, 1: float, 2: float, 3: float}> id => [minX, minY, maxX, maxY] */
    private array $objects = [];

    public function feed(string $line): void
    {
        if ($line === '') {
            return;
        }
        $c = $line[0];

        if ($c === ';') {
            if (str_starts_with($line, '; FLUSH_START')) { $this->inFlush = true; return; }
            if (str_starts_with($line, '; FLUSH_END'))   { $this->inFlush = false; return; }
            if (str_starts_with($line, '; CP TOOLCHANGE START')) { $this->changes++; return; }
            if (str_starts_with($line, '; start printing object, unique label id: ')) {
                $this->object = (int) substr($line, 42);
                return;
            }
            if (str_starts_with($line, '; stop printing object, unique label id: ')) {
                $this->object = null;
                return;
            }
            if (preg_match('/^;\s?(?:TYPE|FEATURE):\s*(.+)$/', $line, $m)) {
                $f = strtolower(trim($m[1]));
                $this->inTower = $f === 'prime tower' || $f === 'wipe tower';
            }
            return;
        }

        if ($c === 'M') {
            // Bambu: M620 S3A = zavést filament 4 (S255 = vysunout)
            if (preg_match('/^M620 S(\d+)A/', $line, $m) && (int) $m[1] < 64) {
                $this->tool = (int) $m[1];
            }
            return;
        }

        if ($c === 'T') {
            if (preg_match('/^T(\d{1,2})(\s|;|$)/', $line, $m)) {
                $this->tool = (int) $m[1];
            }
            return;
        }

        if ($c !== 'G') {
            return;
        }
        $code = strstr($line, ';', true) ?: $line;

        if (($this->inFlush || $this->inTower) && preg_match('/ E(\d*\.?\d+)/', $code, $m)) {
            $e = (float) $m[1];
            if ($this->inFlush) {
                $this->flushMm[$this->tool] = ($this->flushMm[$this->tool] ?? 0) + $e;
            } else {
                $this->towerMm[$this->tool] = ($this->towerMm[$this->tool] ?? 0) + $e;
            }
        }

        // Poloha a půdorys objektu (extruze uvnitř bloku objektu)
        $nx = str_contains($code, 'X') && preg_match('/ X(-?[\d.]+)/', $code, $mx) ? (float) $mx[1] : null;
        $ny = str_contains($code, 'Y') && preg_match('/ Y(-?[\d.]+)/', $code, $my) ? (float) $my[1] : null;
        if ($this->object !== null && ($nx !== null || $ny !== null)
            && preg_match('/ E(\d*\.?\d+)/', $code) && $this->x !== null && $this->y !== null) {
            $id = $this->object;
            $tx = $nx ?? $this->x;
            $ty = $ny ?? $this->y;
            $b  = $this->objects[$id] ?? [$this->x, $this->y, $this->x, $this->y];
            $this->objects[$id] = [
                min($b[0], $this->x, $tx), min($b[1], $this->y, $ty),
                max($b[2], $this->x, $tx), max($b[3], $this->y, $ty),
            ];
        }
        if ($nx !== null) $this->x = $nx;
        if ($ny !== null) $this->y = $ny;
    }

    /** @return array<int, array{0: float, 1: float, 2: float, 3: float}> id objektu => [minX, minY, maxX, maxY] */
    public function objects(): array
    {
        return array_map(fn ($b) => array_map(fn ($v) => round($v, 1), $b), $this->objects);
    }

    public function changes(): int
    {
        return $this->changes;
    }

    /**
     * @param float[] $densities g/cm³ podle nástroje (index 0..)
     * @param float[] $diameters mm podle nástroje
     * @return array{filament_changes: int, waste_flush_g: float, waste_tower_g: float}
     */
    public function result(array $densities, array $diameters): array
    {
        $grams = function (array $mmByTool) use ($densities, $diameters): float {
            $g = 0.0;
            foreach ($mmByTool as $tool => $mm) {
                $d   = (float) ($diameters[$tool] ?? $diameters[0] ?? 1.75) ?: 1.75;
                $rho = (float) ($densities[$tool] ?? $densities[0] ?? 1.24) ?: 1.24;
                $g  += $mm * M_PI * ($d / 2) ** 2 * $rho / 1000;
            }
            return round($g, 1);
        };

        return [
            'filament_changes' => $this->changes,
            'waste_flush_g'    => $grams($this->flushMm),
            'waste_tower_g'    => $grams($this->towerMm),
        ];
    }
}
