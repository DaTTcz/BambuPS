<?php

namespace App\Services;

/**
 * Úpravy naslicovaného gcode před odesláním na tiskárnu.
 *
 * Pracuje vždy na DOČASNÉ KOPII .3mf - originál na disku zůstává netknutý.
 * Upravuje jen to, co slicer dosadil natvrdo už při slicování (teploty,
 * {if}-bloky ve startovním gcode). Věci, které firmware vyhodnocuje za
 * běhu (M1002 judge_flag ... - vyrovnání, kalibrace průtoku, timelapse),
 * se řídí příznaky v MQTT příkazu a gcode kvůli nim měnit nepotřebujeme.
 */
class GcodePatcherService
{
    /** Z-offset, který Bambu startovní gcode přidává pro Textured PEI Plate */
    private const TEXTURED_Z_OFFSET_DEFAULT = '-0.04';   // X1, P1
    private const TEXTURED_Z_OFFSET_A1      = '-0.02';   // A1, A1 mini

    /** Poznámky o tom, co se v posledním patchi změnilo (pro log / toast) */
    public array $notes = [];

    /** Poměr odhadnutého času po vynechání objektů (1 = beze změny) */
    public float $timeRatio = 1.0;

    /**
     * Upraví kopii souboru pro tisk na jinou fyzickou podložku, než pro jakou
     * byl soubor naslicovaný:
     *  - teplota 1. vrstvy (všechny M140/M190 se starou hodnotou ve startovním gcode),
     *  - teplota dalších vrstev (pozdější M140 se starou hodnotou),
     *  - Z-offset pro Textured PEI Plate (G29.1 Z-0.0x) - odebrat / doplnit,
     *  - komentář ;curr_bed_type= ,
     *  - kontrolní součet Metadata/plate_N.gcode.md5.
     *
     * Hledá se podle HODNOTY teploty, ne podle komentáře za příkazem - každý
     * slicer/startovní gcode komentuje jinak (Bambu Studio X1 ";set bed temp",
     * A1 mini "M140 S65" bez komentáře a "M190 S65;set bed temp", vlastní
     * startovní gcode v OrcaSliceru třeba česky).
     *
     * @param array{initial:int, other:int} $from  Teploty podložky ze souboru
     * @param array{initial:int, other:int} $to    Teploty zvolené podložky
     * @return string Cesta k dočasnému patchnutému souboru (smaž po použití!)
     * @throws \RuntimeException Pokud se soubor nepodaří bezpečně upravit
     */
    public function patchForBedPlate(
        string $sourcePath,
        int $plateIndex,
        string $fromPlate,
        string $toPlate,
        array $from,
        array $to,
        ?string $printerModel = null,
    ): string {
        return $this->patch($sourcePath, $plateIndex, [
            'fromPlate' => $fromPlate,
            'toPlate'   => $toPlate,
            'from'      => $from,
            'to'        => $to,
        ], [], $printerModel);
    }

    /**
     * Upraví dočasnou kopii .3mf - podložku (viz patchForBedPlate) a/nebo
     * teplotu trysky.
     *
     * @param array|null $bed        ['fromPlate', 'toPlate', 'from' => [initial, other], 'to' => [initial, other]]
     * @param array      $nozzleMap  ['first' => [°C => °C], 'rest' => [°C => °C], 'insert' => °C|null], viz patchNozzleContent
     * @param int[]      $skipObjects ID objektů (unique label id), které se z gcode odstraní
     * @return string Cesta k dočasnému patchnutému souboru (smaž po použití!)
     */
    public function patch(string $sourcePath, int $plateIndex, ?array $bed, array $nozzleMap = [], ?string $printerModel = null, array $skipObjects = []): string
    {
        $this->notes     = [];
        $this->timeRatio = 1.0;
        // gcode velkých projektů má desítky MB a pracuje se s ním v paměti
        @ini_set('memory_limit', '1024M');

        $tempPath = tempnam(sys_get_temp_dir(), 'bambups_patch_') . '.3mf';
        if (!copy($sourcePath, $tempPath)) {
            throw new \RuntimeException('Nepodařilo se vytvořit dočasnou kopii souboru.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tempPath) !== true) {
            @unlink($tempPath);
            throw new \RuntimeException('Nepodařilo se otevřít 3mf pro úpravu gcode.');
        }

        try {
            $gcodeEntry = "Metadata/plate_{$plateIndex}.gcode";
            $content    = $zip->getFromName($gcodeEntry);
            if ($content === false) {
                throw new \RuntimeException("Soubor {$gcodeEntry} nebyl v 3mf nalezen.");
            }

            if ($bed) {
                $content = $this->patchBedContent($content, $bed['fromPlate'], $bed['toPlate'], $bed['from'], $bed['to'], $printerModel);
            }
            if ($nozzleMap) {
                $content = $this->patchNozzleContent($content, $nozzleMap);
            }
            if ($skipObjects) {
                $content = $this->patchSkipObjectsContent($content, $skipObjects);

                // slice_info: odhad času (tiskárna ho ukáže při startu) a objekty
                // označené jako vynechané - jinak je tiskárna nabízí k vynechání
                // dál a v jejím seznamu vypadají, že se tisknou
                $si = $zip->getFromName('Metadata/slice_info.config');
                if ($si !== false) {
                    $skipSet = array_map('intval', $skipObjects);
                    $si = preg_replace_callback('/<plate>.*?<\/plate>/s', function ($pm) use ($plateIndex, $skipSet) {
                        if (!preg_match('/key="index" value="' . $plateIndex . '"/', $pm[0])) {
                            return $pm[0];
                        }
                        $plate = $pm[0];
                        if ($this->timeRatio < 1.0) {
                            $plate = preg_replace_callback('/(key="prediction" value=")(\d+)(")/', fn ($x) =>
                                $x[1] . (int) round($x[2] * $this->timeRatio) . $x[3], $plate);
                        }
                        return preg_replace_callback('/<object\b[^>]*>/', function ($om) use ($skipSet) {
                            if (!preg_match('/identify_id="(\d+)"/', $om[0], $id) || !in_array((int) $id[1], $skipSet, true)) {
                                return $om[0];
                            }
                            return str_contains($om[0], 'skipped="')
                                ? preg_replace('/skipped="[^"]*"/', 'skipped="true"', $om[0])
                                : preg_replace('/\s*\/?>$/', ' skipped="true"$0', $om[0], 1);
                        }, $plate);
                    }, $si);
                    $zip->addFromString('Metadata/slice_info.config', $si);
                }
            }

            $zip->addFromString($gcodeEntry, $content);

            // Kontrolní součet - Bambu ho ukládá velkými písmeny
            $md5Entry = $gcodeEntry . '.md5';
            if ($zip->locateName($md5Entry) !== false) {
                $zip->addFromString($md5Entry, strtoupper(md5($content)));
            }

            $zip->close();
        } catch (\Throwable $e) {
            @$zip->close();
            @unlink($tempPath);
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage(), 0, $e);
        }

        return $tempPath;
    }

    /**
     * Teplota trysky filamentu (0-indexovaný) z project_settings.
     * Novější Bambu Studio / OrcaSlicer mají pole po (filament × varianta
     * trysky) - např. 16 hodnot pro 8 filamentů (Standard / High Flow).
     * Pozici najdeme přes filament_self_index + filament_extruder_variant.
     *
     * @return array{initial:int, other:int}|null
     */
    public static function filamentNozzleTemps(array $settings, int $filament): ?array
    {
        $nt   = array_values((array) ($settings['nozzle_temperature'] ?? []));
        $nti  = array_values((array) ($settings['nozzle_temperature_initial_layer'] ?? []));
        $self = array_values((array) ($settings['filament_self_index'] ?? []));
        $pos  = $filament;

        if ($self && count($self) === count($nt)) {
            $volume   = (string) ((array) ($settings['nozzle_volume_type'] ?? ['Standard']))[0];
            $variants = array_values((array) ($settings['filament_extruder_variant'] ?? []));
            $cands    = array_keys(array_filter($self, fn ($v) => (int) $v === $filament + 1));
            $pos      = $cands[0] ?? null;
            foreach ($cands as $k) {
                if (isset($variants[$k]) && str_ends_with($variants[$k], $volume)) {
                    $pos = $k;
                    break;
                }
            }
        }

        if ($pos === null || empty($nt[$pos])) {
            return null;
        }
        return ['initial' => (int) ($nti[$pos] ?? $nt[$pos]), 'other' => (int) $nt[$pos]];
    }

    /**
     * Teploty trysky pro filamenty desky: [index filamentu => ['initial' => int, 'other' => int]].
     * Bere je z project_settings.config (pole po filamentech), u souboru bez
     * nich z gcode (první M109 ve startu = 1. vrstva, první M104 po ní = další vrstvy).
     *
     * @param array $filaments filamenty desky z metadat (kvůli extruder_index)
     */
    public function nozzleTemps(string $sourcePath, int $plateIndex, array $filaments): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($sourcePath) !== true) {
            return [];
        }
        $settings  = json_decode((string) $zip->getFromName('Metadata/project_settings.config'), true) ?: [];
        $gcode     = $zip->getFromName("Metadata/plate_{$plateIndex}.gcode");
        $sliceInfo = $zip->getFromName('Metadata/slice_info.config');
        $zip->close();

        // Indexy filamentů desky přímo ze slice_info (metadata starších
        // souborů extruder_index nemají -> brala by se teplota 1. filamentu)
        $ids = [];
        if ($sliceInfo !== false && ($xml = @simplexml_load_string($sliceInfo))) {
            foreach ($xml->plate as $plate) {
                $idx = null;
                foreach ($plate->metadata as $m) {
                    if ((string) $m['key'] === 'index') {
                        $idx = (int) $m['value'];
                    }
                }
                if ($idx === $plateIndex) {
                    foreach ($plate->filament as $f) {
                        $ids[] = ((int) $f['id']) - 1;
                    }
                }
            }
        }

        // Hlavička gcode (; nozzle_temperature = 210,...,225) má teploty už
        // vyřešené po filamentech pro zvolenou trysku - nejspolehlivější zdroj
        $hdr = [];
        if ($gcode !== false) {
            foreach (['other' => 'nozzle_temperature', 'initial' => 'nozzle_temperature_initial_layer'] as $k => $key) {
                if (preg_match('/^; ' . $key . ' = ([\d,]+)\s*$/m', $gcode, $m)) {
                    $hdr[$k] = array_map('intval', explode(',', $m[1]));
                }
            }
        }

        $result = [];
        foreach (array_values($filaments) as $i => $f) {
            $idx = (int) ($ids[$i] ?? $f['extruder_index'] ?? $i);
            if (!empty($hdr['other'][$idx])) {
                $result[$i] = [
                    'initial' => $hdr['initial'][$idx] ?? $hdr['other'][$idx],
                    'other'   => $hdr['other'][$idx],
                ];
            } elseif ($t = self::filamentNozzleTemps($settings, $idx)) {
                $result[$i] = $t;
            }
        }

        // Fallback z gcode - jen u jednoho filamentu, u víc nejde přiřadit
        if (!$result && $gcode !== false && count($filaments) <= 1) {
            $split = $this->findFirstLayerOffset($gcode) ?? strlen($gcode);
            // Poslední M109 před 1. vrstvou (dřívější bývají teploty pro čištění trysky)
            $in = preg_match_all('/^M109 S(\d+)/m', substr($gcode, 0, $split), $m) ? (int) end($m[1]) : 0;
            $o  = preg_match('/^M104 S([1-9]\d*)/m', substr($gcode, $split), $m2) ? (int) $m2[1] : $in;
            if ($in > 0) {
                $result[0] = ['initial' => $in, 'other' => $o ?: $in];
            }
        }

        return $result;
    }

    /**
     * Přepíše teplotu trysky v M104/M109 podle HODNOTY, zvlášť pro 1. vrstvu
     * (startovní gcode + 1. vrstva) a pro zbytek tisku. Teploty, které
     * startovní gcode používá jinak (čištění trysky, vyfukování při výměně
     * filamentu), mají jiné hodnoty a zůstanou.
     *
     * Když má soubor stejnou teplotu pro 1. i další vrstvy, slicer na začátku
     * 2. vrstvy žádný M104 nedává - pak ho appka vloží ($insertRest).
     *
     * @param array{first?: array, rest?: array, insert?: int|null} $nozzle
     *        first/rest = [původní °C => nová °C], insert = °C pro vložený M104 na 2. vrstvě
     */
    private function patchNozzleContent(string $content, array $nozzle): string
    {
        $clean = fn (array $map) => array_filter($map, fn ($to, $from) => (int) $from > 0 && (int) $to > 0 && (int) $from !== (int) $to, ARRAY_FILTER_USE_BOTH);
        $first  = $clean($nozzle['first'] ?? []);
        $rest   = $clean($nozzle['rest'] ?? []);
        $insert = $nozzle['insert'] ?? null;
        if (!$first && !$rest && !$insert) {
            return $content;
        }

        // Hranice = začátek 2. vrstvy
        $split = $this->findLayerOffset($content, 2);
        if ($split === null) {
            throw new \RuntimeException('V gcode nebyl nalezen začátek 2. vrstvy - soubor nebyl upraven.');
        }
        $partA = substr($content, 0, $split);
        $partB = substr($content, $split);

        $replace = function (string $part, array $map, int &$count): string {
            if (!$map) {
                return $part;
            }
            return preg_replace_callback(
                '/^(M10[49] (?:T\d+ )?S)(\d+)(?:\.0+)?(?![\d.])/m',
                function ($m) use ($map, &$count) {
                    $v = (int) $m[2];
                    if (!isset($map[$v])) {
                        return $m[0];
                    }
                    $count++;
                    return $m[1] . (int) $map[$v];
                },
                $part
            );
        };

        $countA = $countB = 0;
        $partA  = $replace($partA, $first, $countA);
        $partB  = $replace($partB, $rest, $countB);

        if ($first && $countA === 0) {
            throw new \RuntimeException('Ve startovním gcode nebyl nalezen příkaz s teplotou trysky ' . implode(' / ', array_keys($first)) . ' °C - soubor nebyl upraven.');
        }

        // Vložit M104 na začátek 2. vrstvy (za řádek se značkou změny vrstvy)
        if ($insert) {
            $eol   = strpos($partB, "\n");
            $eol   = $eol === false ? strlen($partB) : $eol + 1;
            $partB = substr($partB, 0, $eol) . 'M104 S' . (int) $insert . " ; BambuPS: teplota trysky od 2. vrstvy\n" . substr($partB, $eol);
            $this->notes[] = "vložen M104 S{$insert} na začátek 2. vrstvy";
        }

        // Informativní hlavička slicéru (; nozzle_temperature = 220,220)
        $content = preg_replace_callback(
            '/^(; (nozzle_temperature(?:_initial_layer)?) = )([\d,]+)$/m',
            function ($m) use ($first, $rest) {
                $map = $m[2] === 'nozzle_temperature_initial_layer' ? $first : $rest;
                return $m[1] . implode(',', array_map(fn ($v) => $map[(int) $v] ?? $v, explode(',', $m[3])));
            },
            $partA . $partB
        );

        foreach ($first as $from => $to) {
            $this->notes[] = "tryska 1. vrstva {$from} → {$to} °C";
        }
        foreach ($rest as $from => $to) {
            $this->notes[] = "tryska další vrstvy {$from} → {$to} °C";
        }
        $this->notes[] = "({$countA}+{$countB}× M104/M109)";

        return $content;
    }

    /** Pozice začátku N-té vrstvy (značka změny vrstvy) */
    private function findLayerOffset(string $content, int $n): ?int
    {
        foreach (["\n; CHANGE_LAYER", "\n;LAYER_CHANGE"] as $marker) {
            $pos = -1;
            for ($i = 0; $i < $n; $i++) {
                $pos = strpos($content, $marker, $pos + 1);
                if ($pos === false) {
                    break;
                }
            }
            if ($pos !== false && $pos >= 0) {
                return $pos + 1;
            }
        }
        return null;
    }

    /** Brim: o kolik dál musí být tištěný objekt, aby se úsek vynechal (mm) */
    private const BRIM_MARGIN = 1.0;

    /** Brim: mřížka pro hledání nejbližšího objektu (mm) */
    private const BRIM_CELL = 2.0;

    /**
     * Odebere brim vynechaných objektů - jen tam, kde je jasné, komu patří.
     *
     * Bambu/Orca píše brim v 1. vrstvě mimo bloky M624/M625 a brimy
     * sousedních objektů často slije do jednoho. Každý úsek brimu proto
     * posuzujeme po celých smyčkách (souvislá řada extruzí) - smyčka se
     * odebere celá, nebo zůstane celá (viz pravidla níž). Co jasné není
     * (slitá smyčka kolem obou objektů), zůstává - raději větší brim než
     * useknutý.
     *
     * Odebraná extruze se nahradí přejezdem (se zatažením filamentu), ať se
     * další tištěný úsek nenatáhne přes prázdné místo. Přejezdy, wipe
     * a retrakce slicer zůstávají, stav filamentu se tím nemění.
     *
     * @param array<int,int> $idOfBit bit masky -> ID objektu
     */
    private function stripSkippedBrims(string $content, array $skipIds, array $idOfBit): string
    {
        if (!str_contains($content, '; FEATURE: Brim')) {
            return $content;
        }

        // Rozsah 1. vrstvy (první a druhý "; CHANGE_LAYER")
        $l1 = strpos($content, "; CHANGE_LAYER");
        if ($l1 === false) {
            return $content;
        }
        $l2 = strpos($content, "; CHANGE_LAYER", $l1 + 10);
        $l2 = $l2 === false ? strlen($content) : $l2;
        $layer = substr($content, $l1, $l2 - $l1);
        if (!str_contains($layer, '; FEATURE: Brim')) {
            return $content;
        }

        $lines = preg_split('/(?<=\n)/', $layer);

        // 1) Geometrie objektů: body extruze 1. vrstvy v blocích jednoho objektu
        $grid   = [];
        $objSum = [];   // ID -> [Σx, Σy, n] pro střed objektu
        $x = $y = null;
        $stack = [];
        $hasKept = $hasSkip = false;
        foreach ($lines as $l) {
            if (str_starts_with($l, 'M624 ')) {
                $stack[] = $this->maskValue(strtok(substr($l, 5), " \t\r\n;"));
                continue;
            }
            if (str_starts_with($l, 'M625')) {
                array_pop($stack);
                continue;
            }
            if (!preg_match('/^G[0-3](?:\s|$)/', $l)) {
                continue;
            }
            $v  = $this->moveValues($l);
            $nx = $v['X'] ?? $x;
            $ny = $v['Y'] ?? $y;
            $top = $stack ? end($stack) : 0;
            if ($top && isset($idOfBit[$top]) && ($v['E'] ?? 0) > 0 && $x !== null && $nx !== null) {
                $id = $idOfBit[$top];
                // úsečku navzorkovat po ~1 mm
                $n = max(1, (int) ceil(hypot($nx - $x, $ny - $y)));
                for ($i = 0; $i <= $n; $i++) {
                    $px = $x + ($nx - $x) * $i / $n;
                    $py = $y + ($ny - $y) * $i / $n;
                    $grid[(int) floor($px / self::BRIM_CELL)][(int) floor($py / self::BRIM_CELL)][$id] = [$px, $py];
                    $objSum[$id][0] = ($objSum[$id][0] ?? 0) + $px;
                    $objSum[$id][1] = ($objSum[$id][1] ?? 0) + $py;
                    $objSum[$id][2] = ($objSum[$id][2] ?? 0) + 1;
                }
                in_array($id, $skipIds, true) ? $hasSkip = true : $hasKept = true;
            }
            $x = $nx;
            $y = $ny;
        }
        if (!$hasSkip) {
            return $content;
        }

        // Nejbližší vzdálenost k jednotlivým objektům (do ~30 mm)
        $nearest = function (float $px, float $py) use ($grid): array {
            $cx = (int) floor($px / self::BRIM_CELL);
            $cy = (int) floor($py / self::BRIM_CELL);
            $best = [];
            for ($r = 0; $r <= 15; $r++) {
                for ($i = $cx - $r; $i <= $cx + $r; $i++) {
                    for ($j = $cy - $r; $j <= $cy + $r; $j++) {
                        if (max(abs($i - $cx), abs($j - $cy)) !== $r || !isset($grid[$i][$j])) {
                            continue;
                        }
                        foreach ($grid[$i][$j] as $id => [$gx, $gy]) {
                            $d = hypot($gx - $px, $gy - $py);
                            if (!isset($best[$id]) || $d < $best[$id]) {
                                $best[$id] = $d;
                            }
                        }
                    }
                }
                // dál už nic bližšího (o víc než okraj) nenajdeme
                if ($best && ($r - 1) * self::BRIM_CELL > min($best) + self::BRIM_MARGIN + self::BRIM_CELL) {
                    break;
                }
            }
            return $best;
        };
        // 2) Smyčky brimu v 1. vrstvě (mimo bloky objektů) = souvislé řady extruzí.
        //    Smyčka se odebere celá, nebo zůstane celá - jinak by zbyly srpky.
        $n      = count($lines);
        $pos    = [];          // index řádku -> [x0, y0, x1, y1] u extruzí brimu
        $runOf  = [];          // index řádku -> číslo smyčky
        $runs   = [];          // číslo smyčky -> [indexy]
        $x = $y = null;
        $depth  = 0;
        $inBrim = false;
        $run    = null;
        for ($k = 0; $k < $n; $k++) {
            $l = $lines[$k];
            if (str_starts_with($l, 'M624 ')) {
                $depth++;
                $run = null;
            } elseif (str_starts_with($l, 'M625')) {
                $depth = max(0, $depth - 1);
                $run = null;
            } elseif (str_starts_with($l, '; FEATURE: ')) {
                $inBrim = $depth === 0 && str_starts_with($l, '; FEATURE: Brim');
                $run = null;
            } elseif (str_starts_with($l, '; start printing object')) {
                $inBrim = false;
                $run = null;
            }
            if (!preg_match('/^G[0-3](?:\s|$)/', $l)) {
                continue;
            }
            $v  = $this->moveValues($l);
            $nx = $v['X'] ?? $x;
            $ny = $v['Y'] ?? $y;
            $extrudes = ($v['E'] ?? 0) > 0 && (isset($v['X']) || isset($v['Y']));
            if ($inBrim && $depth === 0 && $extrudes && $x !== null && $nx !== null) {
                if ($run === null) {
                    $run = count($runs);
                    $runs[$run] = [];
                }
                $runs[$run][] = $k;
                $runOf[$k]    = $run;
                $pos[$k]      = [$x, $y, $nx, $ny];
            } elseif ($extrudes || isset($v['X']) || isset($v['Y'])) {
                $run = null;   // přejezd nebo jiná extruze ukončí smyčku
            }
            $x = $nx;
            $y = $ny;
        }

        // Komu smyčka patří:
        //  a) objektu, ke kterému se nejvíc přiblíží - brim se u objektu ořezává
        //     podle obrysu, takže se ho smyčka (i "ucho" ve tvaru C) dotýká;
        //  b) když se dotýká vynechaného i tištěného (ořezaná o oba), rozhodne
        //     střed smyčky - ucho leží středem u rohu svého objektu.
        // Smyčka, která obepíná tištěný objekt (slitý brim kolem obou), zůstává.
        $keptCenters = [];
        foreach ($objSum as $id => [$sx, $sy, $cnt]) {
            if (!in_array($id, $skipIds, true)) {
                $keptCenters[] = [$sx / $cnt, $sy / $cnt];
            }
        }
        $inside = function (array $poly, float $px, float $py): bool {
            $in = false;
            for ($a = 0, $b = count($poly) - 1; $a < count($poly); $b = $a++) {
                [$xa, $ya] = $poly[$a];
                [$xb, $yb] = $poly[$b];
                if (($ya > $py) !== ($yb > $py) && $px < ($xb - $xa) * ($py - $ya) / (($yb - $ya) ?: 1e-9) + $xa) {
                    $in = !$in;
                }
            }
            return $in;
        };
        $splitSides = function (array $best) use ($skipIds): array {
            $dSkip = $dKept = INF;
            foreach ($best as $id => $d) {
                in_array($id, $skipIds, true) ? $dSkip = min($dSkip, $d) : $dKept = min($dKept, $d);
            }
            return [$dSkip, $dKept];
        };

        $drop = [];   // index řádku => true
        foreach ($runs as $idx) {
            $minSkip = $minKept = INF;
            $poly = [];
            $sx = $sy = 0.0;
            $runLen = 0.0;
            foreach ($idx as $k) {
                [$x0, $y0, $x1, $y1] = $pos[$k];
                $runLen += hypot($x1 - $x0, $y1 - $y0);
                if (!$poly) {
                    $poly[] = [$x0, $y0];
                }
                $poly[] = [$x1, $y1];
                foreach ([[$x0, $y0], [($x0 + $x1) / 2, ($y0 + $y1) / 2], [$x1, $y1]] as [$px, $py]) {
                    [$ds, $dk] = $splitSides($nearest($px, $py));
                    $minSkip = min($minSkip, $ds);
                    $minKept = min($minKept, $dk);
                }
            }
            if ($minSkip === INF) {
                continue;                                   // u vynechaného vůbec neleží
            }
            if ($minKept - $minSkip > self::BRIM_MARGIN) {
                $own = true;                                // a) jednoznačně jeho
            } elseif ($minSkip - $minKept > self::BRIM_MARGIN) {
                $own = false;                               // jednoznačně tištěného
            } else {
                foreach ($poly as [$px, $py]) {
                    $sx += $px;
                    $sy += $py;
                }
                [$ds, $dk] = $splitSides($nearest($sx / count($poly), $sy / count($poly)));
                $own = $ds < INF && ($dk - $ds > self::BRIM_MARGIN
                    || ($runLen < 10.0 && $ds < $dk));               // b) podle středu; drobné zbytky stačí blíž
            }
            if ($own && count($poly) >= 3) {
                foreach ($keptCenters as [$cx, $cy]) {
                    if ($inside($poly, $cx, $cy)) {
                        $own = false;                       // obepíná tištěný objekt
                        break;
                    }
                }
            }
            if ($own) {
                foreach ($idx as $k) {
                    $drop[$k] = true;
                }
            }
        }
        $removed = count($drop);
        $keptSeg = count($runOf) - $removed;

        // 3) Výstup: odebrané extruze vynechat; když po nich tištěná extruze
        //    navazuje bez přejezdu, vložit přejezd se zatažením filamentu
        $out  = [];
        $gap  = false;
        $x = $y = null;
        $feed = null;
        for ($k = 0; $k < $n; $k++) {
            $l = $lines[$k];
            if (!preg_match('/^G[0-3](?:\s|$)/', $l)) {
                $out[] = $l;
                continue;
            }
            $v  = $this->moveValues($l);
            $nx = $v['X'] ?? $x;
            $ny = $v['Y'] ?? $y;
            if (isset($v['F'])) {
                $feed = $v['F'];
            }
            if (isset($drop[$k])) {
                $gap = true;
                $x = $nx;
                $y = $ny;
                continue;
            }
            $extrudes = ($v['E'] ?? 0) > 0 && (isset($v['X']) || isset($v['Y']));
            if ($gap && $extrudes) {
                $out[] = "G1 E-.8 F1800 ; BambuPS: přejezd přes vynechaný brim\n";
                $out[] = sprintf("G1 X%.3f Y%.3f F24000\n", $x, $y);
                $out[] = "G1 E.8 F1800\n";
                if ($feed && !isset($v['F'])) {
                    $out[] = 'G1 F' . rtrim(rtrim(sprintf('%.3f', $feed), '0'), '.') . "\n";
                }
            }
            if (isset($v['X']) || isset($v['Y'])) {
                $gap = false;   // absolutní pohyb - poloha zase sedí
            }
            $out[] = $l;
            $x = $nx;
            $y = $ny;
        }

        if ($removed === 0) {
            return $content;
        }
        $this->notes[] = "brim vynechaných objektů: {$removed} z " . ($removed + $keptSeg) . ' úseků';

        return substr($content, 0, $l1) . implode('', $out) . substr($content, $l2);
    }

    /** Hodnoty X/Y/Z/E/F z řádku pohybu (bez komentáře) */
    private function moveValues(string $line): array
    {
        $code = strstr($line, ';', true);
        $code = $code === false ? $line : $code;
        preg_match_all('/([XYZEF])(-?[\d.]+)/', $code, $mm, PREG_SET_ORDER);
        $v = [];
        foreach ($mm as $m) {
            $v[$m[1]] = (float) $m[2];
        }
        return $v;
    }

    /**
     * Odstraní z gcode vynechané objekty.
     *
     * Bambu/Orca obalují každý objekt v každé vrstvě do bloku
     * "M624 <maska>" ... "M625", kde maska (base64, 64 bitů) říká, kterým
     * objektům blok patří. Stejné bloky přeskakuje firmware, když se objekt
     * vynechá během tisku. Tady se odstraní pohyby (G0-G3) z bloků, jejichž
     * všechny objekty jsou vynechané; M-příkazy (ventilátor, teploty,
     * průběh) zůstávají. Každý blok si na začátku sám nastaví Z, XY i
     * vytažení filamentu, takže vynechání nenaruší zbytek tisku.
     */

    private function patchSkipObjectsContent(string $content, array $skipIds): string
    {
        // ID objektu -> bit masky ("; start printing object, unique label id: N" + M624 hned za ním)
        preg_match_all('/^; start printing object, unique label id: (\d+)\r?\n(?:;[^\n]*\n)*M624 (\S+)/m', $content, $mm, PREG_SET_ORDER);
        $bitOf = [];
        foreach ($mm as $m) {
            $mask = $this->maskValue($m[2]);
            if ($mask !== 0 && ($mask & ($mask - 1)) === 0 && !isset($bitOf[(int) $m[1]])) {
                $bitOf[(int) $m[1]] = $mask;
            }
        }

        $skipMask = 0;
        foreach (array_unique(array_map('intval', $skipIds)) as $id) {
            if (!isset($bitOf[$id])) {
                throw new \RuntimeException('Objekt nejde vynechat - soubor nemá značky objektů. Slicuj ho se zapnutým "Label objects".');
            }
            $skipMask |= $bitOf[$id];
        }
        if ($skipMask === 0) {
            return $content;
        }

        // Brimy jsou mimo bloky objektů (M624/M625) - každý těsně před
        // blokem objektu, ke kterému patří. Vynechanému objektu je odebereme.
        $content = $this->stripSkippedBrims($content, array_map('intval', $skipIds), array_flip($bitOf));

        $out     = fopen('php://temp/maxmemory:' . (128 * 1024 * 1024), 'w+');
        $stack   = [];
        $removed = 0;
        $pos     = 0;
        $len     = strlen($content);

        // Odhad času pro přepočet průběhu (M73 P.. R..): původní a upravený soubor
        $orig  = ['x' => null, 'y' => null, 'z' => 0.0, 'f' => 3000.0];
        $kept  = $orig;
        $segO  = [0.0];   // čas po jednotlivých M73 (s)
        $segK  = [0.0];
        $m73R  = [];      // původní zbývající minuty u každého M73
        $moveTime = function (array &$st, string $code): float {
            preg_match_all('/([XYZEF])(-?[\d.]+)/', $code, $mm, PREG_SET_ORDER);
            $v = [];
            foreach ($mm as $m) {
                $v[$m[1]] = (float) $m[2];
            }
            if (isset($v['F']) && $v['F'] > 0) {
                $st['f'] = $v['F'];
            }
            $dx = isset($v['X']) && $st['x'] !== null ? $v['X'] - $st['x'] : 0.0;
            $dy = isset($v['Y']) && $st['y'] !== null ? $v['Y'] - $st['y'] : 0.0;
            $dz = isset($v['Z']) ? $v['Z'] - $st['z'] : 0.0;
            $d  = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
            if ($d == 0.0 && isset($v['E'])) {
                $d = abs($v['E']);
            }
            if (isset($v['X'])) $st['x'] = $v['X'];
            if (isset($v['Y'])) $st['y'] = $v['Y'];
            if (isset($v['Z'])) $st['z'] = $v['Z'];
            return $d > 0 ? $d / ($st['f'] / 60) : 0.0;
        };

        while ($pos < $len) {
            $nl   = strpos($content, "\n", $pos);
            $end  = $nl === false ? $len : $nl + 1;
            $line = substr($content, $pos, $end - $pos);
            $pos  = $end;

            if (str_starts_with($line, 'M624 ')) {
                $stack[] = $this->maskValue(strtok(substr($line, 5), " \t\r\n;"));
            } elseif (str_starts_with($line, 'M625')) {
                array_pop($stack);
            } elseif (preg_match('/^M73 P\d+ R(\d+)/', $line, $mr)) {
                // průběh a zbývající čas - přepíše se po spočítání času
                $m73R[] = (int) $mr[1];
                $segO[] = 0.0;
                $segK[] = 0.0;
                fwrite($out, "\x00M73#" . (count($m73R) - 1) . "\n");
                continue;
            } elseif (($line[0] ?? '') === 'G' && preg_match('/^G[0-3](?:\s|$)/', $line)) {
                $code = strstr($line, ';', true) ?: $line;
                $segO[count($segO) - 1] += $moveTime($orig, $code);
                $top = $stack ? end($stack) : 0;
                // Blok patří jen vynechaným objektům -> pohyb vynechat
                if ($top !== 0 && ($top & ~$skipMask) === 0) {
                    $removed++;
                    continue;
                }
                $segK[count($segK) - 1] += $moveTime($kept, $code);
            }
            fwrite($out, $line);
        }

        rewind($out);
        $result = stream_get_contents($out);
        fclose($out);

        if ($removed === 0) {
            throw new \RuntimeException('Vynechané objekty se v gcode nepodařilo najít - soubor nebyl upraven.');
        }

        // Přepočet průběhu: zbývající čas u každého M73 se zkrátí v poměru
        // odhadnutého času zbytku tisku (upravený / původní)
        $n      = count($m73R);
        $sufO   = array_fill(0, $n + 1, 0.0);
        $sufK   = array_fill(0, $n + 1, 0.0);
        for ($k = $n - 1; $k >= 0; $k--) {
            $sufO[$k] = $sufO[$k + 1] + $segO[$k + 1];
            $sufK[$k] = $sufK[$k + 1] + $segK[$k + 1];
        }
        $newR = [];
        for ($k = 0; $k < $n; $k++) {
            $newR[$k] = $sufO[$k] > 0 ? (int) round($m73R[$k] * $sufK[$k] / $sufO[$k]) : $m73R[$k];
        }
        $total  = max(1, $newR[0] ?? 1);
        $result = preg_replace_callback('/^\x00M73#(\d+)$/m', function ($m) use ($newR, $total) {
            $r = $newR[(int) $m[1]];
            return 'M73 P' . max(0, min(100, (int) round(100 * (1 - $r / $total)))) . ' R' . $r;
        }, $result);

        $totalO = array_sum($segO);
        $this->timeRatio = $totalO > 0 ? array_sum($segK) / $totalO : 1.0;

        $this->notes[] = 'vynecháno objektů: ' . count(array_unique($skipIds)) . " ({$removed} řádků pohybu, čas × "
            . round($this->timeRatio, 2) . ')';

        return $result;
    }

    /** Maska objektů z M624 (base64, 8 bajtů little-endian) */
    private function maskValue(string|false $b64): int
    {
        $raw = $b64 === false ? false : base64_decode($b64, true);
        if ($raw === false || $raw === '') {
            return 0;
        }
        return unpack('P', str_pad(substr($raw, 0, 8), 8, "\0"))[1];
    }

    /** Úprava gcode pro jinou podložku (popis viz patchForBedPlate) */
    private function patchBedContent(string $content, string $fromPlate, string $toPlate, array $from, array $to, ?string $printerModel): string
    {
        // Startovní gcode = vše před první změnou vrstvy
        $split = $this->findFirstLayerOffset($content);
        if ($split === null) {
            throw new \RuntimeException('V gcode nebyl nalezen začátek první vrstvy - soubor nebyl upraven.');
        }
        $start = substr($content, 0, $split);
        $rest  = substr($content, $split);

        // 1) Teplota 1. vrstvy ve startovním gcode
        $fromInitial = (int) $from['initial'];
        $toInitial   = (int) $to['initial'];
        if ($fromInitial > 0 && $fromInitial !== $toInitial) {
            $start = preg_replace(
                '/^(M1[49]0 S)' . $fromInitial . '(?:\.0+)?(?![\d.])/m',
                '${1}' . $toInitial,
                $start,
                -1,
                $count
            );
            // Bez nahrazeného M190 (čekání na teplotu) by tiskárna mohla
            // začít tisknout na podložce se špatnou teplotou - radši nic.
            if (!preg_match('/^M190 S' . $toInitial . '(?!\d)/m', $start)) {
                throw new \RuntimeException("Ve startovním gcode nebyl nalezen M190 S{$fromInitial} - soubor nebyl upraven.");
            }
            $this->notes[] = "teplota 1. vrstvy {$fromInitial} → {$toInitial} °C ({$count}×)";
        }

        // 2) Teplota dalších vrstev (jen nenulová - M140 S0 na konci vypíná podložku)
        $fromOther = (int) $from['other'];
        $toOther   = (int) $to['other'];
        if ($fromOther > 0 && $fromOther !== $toOther) {
            $rest = preg_replace(
                '/^(M140 S)' . $fromOther . '(?:\.0+)?(?![\d.])/m',
                '${1}' . $toOther,
                $rest,
                -1,
                $countOther
            );
            if ($countOther > 0) {
                $this->notes[] = "teplota dalších vrstev {$fromOther} → {$toOther} °C ({$countOther}×)";
            }
        }

        // 3) Z-offset pro Textured PEI Plate
        $fromTextured = $this->isTexturedPei($fromPlate);
        $toTextured   = $this->isTexturedPei($toPlate);

        if ($fromTextured && !$toTextured) {
            $start = preg_replace(
                '/^(G29\.1 Z-0?\.\d+.*Textured PEI.*)$/mi',
                '; $1 ; BambuPS: vypnuto - tiskne se na ' . $toPlate,
                $start,
                -1,
                $countZ
            );
            if ($countZ > 0) {
                $this->notes[] = 'odebrán Z-offset pro Textured PEI';
            }
        } elseif (!$fromTextured && $toTextured) {
            $offset = $this->texturedOffsetFor($printerModel);
            // Vkládáme jen tam, kde je Bambu značka ;curr_bed_type= -
            // u vlastního startovního gcode (OrcaSlicer) nevíme, kam
            // offset bezpečně dát, tak ho tam nepřidáváme.
            $start = preg_replace(
                '/^(;curr_bed_type=.*)$/m',
                "\$1\nG29.1 Z{$offset} ; for Textured PEI Plate (BambuPS)",
                $start,
                1,
                $countZ
            );
            $this->notes[] = $countZ > 0
                ? "přidán Z-offset {$offset} mm pro Textured PEI"
                : 'Z-offset pro Textured PEI nepřidán (startovní gcode nemá značku ;curr_bed_type=)';
        }

        // 4) Komentář s typem podložky (informativní)
        $start = preg_replace('/^;curr_bed_type=.*$/m', ';curr_bed_type=' . $toPlate, $start, 1);

        return $start . $rest;
    }

    /**
     * Obsahuje gcode desky skenování první vrstvy (lidar)? Příkazy M976/M977
     * dosadí slicer jen při scan_first_layer = 1 - bez nich příznak
     * layer_inspect v MQTT nemá co spustit.
     */
    public function hasFirstLayerScan(string $sourcePath, int $plateIndex): ?bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($sourcePath) !== true) {
            return null;
        }
        $content = $zip->getFromName("Metadata/plate_{$plateIndex}.gcode");
        $zip->close();

        if ($content === false) {
            return null;
        }

        $split = $this->findFirstLayerOffset($content) ?? strlen($content);
        return (bool) preg_match('/^M97[67] S/m', substr($content, 0, $split));
    }

    /** Pozice první změny vrstvy (Bambu Studio i OrcaSlicer: "; CHANGE_LAYER", starší ";LAYER_CHANGE") */
    private function findFirstLayerOffset(string $content): ?int
    {
        foreach (["\n; CHANGE_LAYER", "\n;LAYER_CHANGE"] as $marker) {
            $pos = strpos($content, $marker);
            if ($pos !== false) {
                return $pos + 1;
            }
        }
        return null;
    }

    private function isTexturedPei(string $plate): bool
    {
        return stripos($plate, 'textured pei') !== false;
    }

    private function texturedOffsetFor(?string $printerModel): string
    {
        return ($printerModel && preg_match('/\bA1\b/i', $printerModel))
            ? self::TEXTURED_Z_OFFSET_A1
            : self::TEXTURED_Z_OFFSET_DEFAULT;
    }
}
