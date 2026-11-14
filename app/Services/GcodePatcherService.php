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
     * @return string Cesta k dočasnému patchnutému souboru (smaž po použití!)
     */
    public function patch(string $sourcePath, int $plateIndex, ?array $bed, array $nozzleMap = [], ?string $printerModel = null): string
    {
        $this->notes = [];

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
        $settings = json_decode((string) $zip->getFromName('Metadata/project_settings.config'), true) ?: [];
        $gcode    = $zip->getFromName("Metadata/plate_{$plateIndex}.gcode");
        $zip->close();

        $other   = (array) ($settings['nozzle_temperature'] ?? []);
        $initial = (array) ($settings['nozzle_temperature_initial_layer'] ?? []);

        $result = [];
        foreach (array_values($filaments) as $i => $f) {
            $idx = (int) ($f['extruder_index'] ?? $i);
            $o   = (int) ($other[$idx] ?? 0);
            $in  = (int) ($initial[$idx] ?? $o);
            if ($o > 0) {
                $result[$i] = ['initial' => $in ?: $o, 'other' => $o];
            }
        }

        // Fallback z gcode - jen u jednoho filamentu, u víc nejde přiřadit
        if (!$result && $gcode !== false && count($filaments) <= 1) {
            $split = $this->findFirstLayerOffset($gcode) ?? strlen($gcode);
            $in = preg_match('/^M109 S(\d+)/m', substr($gcode, 0, $split), $m) ? (int) $m[1] : 0;
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
