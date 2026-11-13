<?php

namespace App\Services;

/**
 * Metadata z G-code souborů.
 *
 *  - textový .gcode z OrcaSliceru / Bambu Studia / PrusaSliceru
 *    (HEADER_BLOCK, CONFIG_BLOCK, komentáře "; klíč = hodnota" na konci)
 *  - binární .bgcode z PrusaSliceru (Prusa "GCDE" formát) - čte se jen
 *    z bloků s metadaty a náhledy, samotný G-code (heatshrink) se nerozbaluje.
 *
 * U Prusy navíc: výrobce, počet nástrojů, které nástroje tisk používá
 * (XL toolchanger), spotřeba filamentu po nástrojích a trysky po nástrojích.
 */
class GcodeParserService
{
    public function parse(string $filePath): array
    {
        $magic = @file_get_contents($filePath, false, null, 0, 4);
        if ($magic === 'GCDE') {
            return $this->parseBinary($filePath);
        }

        return $this->parseText($filePath);
    }

    // =====================================================================
    //  Textový G-code
    // =====================================================================

    private function parseText(string $filePath): array
    {
        $metadata      = [];
        $headerData    = [];
        $configData    = [];
        $footer        = [];   // "; klíč = hodnota" mimo bloky (spotřeba, čas)
        $handle        = fopen($filePath, 'r');
        if (!$handle) return [];

        $inHeaderBlock = false;
        $inConfigBlock = false;
        $inThumbnail   = false;
        $thumbnailData = '';
        $thumbnailArea = 0;

        // Ze startovního G-code (příkazy, ne komentáře)
        $m862Model   = null;   // M862.3 P "XL"
        $m862Tools   = [];     // M862.1 T4 P0.4 -> [4 => 0.4]
        $firstBedS   = null;   // první M140/M190 S..
        $firstNozzle = null;   // první M109/M104 [T..] S.. (nenulová)
        $toolChanges = [];     // T0..T15
        $lineNo      = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNo++;
            $line = rtrim($line);

            // Header block
            if ($line === '; HEADER_BLOCK_START') { $inHeaderBlock = true; continue; }
            if ($line === '; HEADER_BLOCK_END')   { $inHeaderBlock = false; continue; }

            // Config block
            if ($line === '; CONFIG_BLOCK_START') { $inConfigBlock = true; continue; }
            if ($line === '; CONFIG_BLOCK_END')   { $inConfigBlock = false; continue; }

            // Thumbnail - jen PNG ("; thumbnail begin WxH len"), QOI/JPG přeskočit.
            // Bereme největší náhled.
            if (preg_match('/^; thumbnail begin (\d+)x(\d+)/', $line, $tm)) {
                $inThumbnail   = true;
                $thumbnailData = '';
                $currentArea   = (int) $tm[1] * (int) $tm[2];
                continue;
            }
            if ($line === '; thumbnail end') {
                $inThumbnail = false;
                if ($thumbnailData && ($currentArea ?? 0) >= $thumbnailArea) {
                    $metadata['thumbnail_data'] = $thumbnailData;
                    $thumbnailArea = $currentArea ?? 0;
                }
                $thumbnailData = '';
                continue;
            }
            if ($inThumbnail) {
                $thumbnailData .= ltrim($line, '; ');
                continue;
            }

            // Header data
            if ($inHeaderBlock && str_starts_with($line, '; ')) {
                $content = substr($line, 2);
                if (preg_match('/total estimated time:\s*(.+)/', $content, $m)) {
                    $metadata['print_time']         = trim($m[1]);
                    $metadata['print_time_seconds'] = $this->parseTime(trim($m[1]));
                }
                if (preg_match('/total layer number:\s*(\d+)/', $content, $m)) {
                    $metadata['total_layer_num'] = (int) $m[1];
                }
                if (preg_match('/max_z_height:\s*([\d.]+)/', $content, $m)) {
                    $metadata['max_z_height'] = (float) $m[1];
                }
                if (preg_match('/^filament:\s*(\d+)/', $content, $m)) {
                    $headerData['filament_count'] = (int) $m[1];
                }
                continue;
            }

            // Config data
            if ($inConfigBlock) {
                if (str_starts_with($line, '; ') && preg_match('/^; ([a-z_0-9]+)\s*=\s*(.*)$/', $line, $m)) {
                    $configData[trim($m[1])] = trim($m[2]);
                }
                continue;
            }

            // Komentáře mimo bloky (spotřeba filamentu, odhad času - konec souboru;
            // PrusaSlicer tu má i celou konfiguraci)
            if (str_starts_with($line, '; ')) {
                if (preg_match('/^; ([^=]+?)\s*=\s*(.*)$/', $line, $m)) {
                    $footer[trim($m[1])] = trim($m[2]);
                }
                continue;
            }

            // Příkazy - jen ty, které nás zajímají
            if ($line === '' || $line[0] === ';') {
                continue;
            }
            if ($m862Model === null && preg_match('/^M862\.3 P\s*"?([A-Za-z0-9 ]+?)"?(\s|;|$)/', $line, $m)) {
                $m862Model = trim($m[1]);
            } elseif (preg_match('/^M862\.1 T(\d+) P([\d.]+)/', $line, $m)) {
                $m862Tools[(int) $m[1]] = (float) $m[2];
            } elseif ($firstBedS === null && preg_match('/^M1[49]0 S(\d+(?:\.\d+)?)/', $line, $m) && (float) $m[1] > 0) {
                $firstBedS = (int) round((float) $m[1]);
            } elseif ($firstNozzle === null && preg_match('/^M10[49](?: T\d+)? S(\d+(?:\.\d+)?)/', $line, $m) && (float) $m[1] > 0 && $lineNo > 1) {
                // M104 S75/S140 apod. z "předehřevu" nechceme - bereme první M109 (čekání na teplotu)
                if (str_starts_with($line, 'M109')) {
                    $firstNozzle = (int) round((float) $m[1]);
                }
            } elseif (preg_match('/^T(\d{1,2})(\s|;|$)/', $line, $m)) {
                $toolChanges[(int) $m[1]] = true;
            }
        }

        fclose($handle);

        // PrusaSlicer textový G-code má konfiguraci jako "; klíč = hodnota" na konci (bez bloku)
        if (empty($configData) && !empty($footer)) {
            $configData = $footer;
        }

        // Odhad času z konce souboru, pokud chybí v hlavičce (Prusa profily)
        if (empty($metadata['print_time'])) {
            $timeStr = $footer['estimated printing time (normal mode)'] ?? ($configData['estimated printing time (normal mode)'] ?? null);
            if ($timeStr) {
                $metadata['print_time']         = $timeStr;
                $metadata['print_time_seconds'] = $this->parseTime($timeStr);
            }
        }

        $usedG  = $this->csvFloats($footer['filament used [g]'] ?? '');
        $usedMm = $this->csvFloats($footer['filament used [mm]'] ?? '');
        if ($usedG) {
            $metadata['filament_used_g'] = round(array_sum($usedG), 2);
        }
        if ($usedMm) {
            $metadata['filament_used_m'] = round(array_sum($usedMm) / 1000, 2);
        }

        if (empty($configData)) {
            return $this->finish($metadata);
        }

        $metadata['printer_model']        = $configData['printer_model'] ?? $m862Model;
        $metadata['layer_height']         = $configData['layer_height'] ?? null;
        $metadata['initial_layer_height'] = $configData['initial_layer_print_height'] ?? ($configData['first_layer_height'] ?? null);
        $metadata['nozzle_temp']          = $this->firstCsvValue($configData['nozzle_temperature'] ?? ($configData['temperature'] ?? ''));
        $metadata['support_type']         = $configData['support_type'] ?? null;
        $metadata['spiral_mode']          = ($configData['spiral_mode'] ?? '0') === '1';
        $metadata['infill_density']       = $configData['sparse_infill_density'] ?? ($configData['fill_density'] ?? null);
        $metadata['infill_pattern']       = $configData['sparse_infill_pattern'] ?? ($configData['fill_pattern'] ?? null);
        $metadata['wall_loops']           = $configData['wall_loops'] ?? ($configData['perimeters'] ?? null);
        $metadata['print_profile']        = $configData['default_print_profile'] ?? ($configData['print_settings_id'] ?? null);
        $metadata['has_gcode']            = true;

        // ---- Podložka (Bambu klíče; Prusa profily mají jen hot_plate_temp atd. po filamentech)
        $bedType              = $configData['curr_bed_type'] ?? '';
        $metadata['bed_type'] = $bedType;

        $knownBedKeys = [
            'cool plate'          => 'cool_plate_temp',
            'engineering plate'   => 'eng_plate_temp',
            'high temp plate'     => 'hot_plate_temp',
            'hot plate'           => 'hot_plate_temp',
            'textured pei plate'  => 'textured_plate_temp',
            'textured cool plate' => 'textured_cool_plate_temp',
            'supertack plate'     => 'supertack_plate_temp',
        ];
        $bedKey = $knownBedKeys[strtolower(trim($bedType))] ?? null;

        if ($bedKey && !empty($configData[$bedKey])) {
            $metadata['bed_temp'] = $this->firstCsvValue($configData[$bedKey]);
            $metadata['bed_temp_initial_layer'] = !empty($configData[$bedKey . '_initial_layer'])
                ? $this->firstCsvValue($configData[$bedKey . '_initial_layer'])
                : $metadata['bed_temp'];
        } elseif (!empty($configData['bed_temperature'])) {
            // PrusaSlicer
            $metadata['bed_temp'] = $this->firstCsvValue($configData['bed_temperature']);
            $metadata['bed_temp_initial_layer'] = $this->firstCsvValue($configData['first_layer_bed_temperature'] ?? $configData['bed_temperature']);
        }

        // ---- Prusa: výrobce, počet nástrojů, použité nástroje
        $isPrusa = $this->isPrusaModel($metadata['printer_model'] ?? '') || $m862Model !== null;
        $nozzles = $this->csvFloats($configData['nozzle_diameter'] ?? '');

        // Které nástroje tisk opravdu používá: spotřeba > 0, jinak M862.1 / T příkazy
        $usedTools = [];
        foreach ($usedG as $i => $g) {
            if ($g > 0) $usedTools[] = $i;
        }
        if (!$usedTools && $m862Tools) {
            $usedTools = array_keys($m862Tools);
        }
        if (!$usedTools && $toolChanges) {
            $usedTools = array_keys(array_filter($toolChanges, fn ($v, $k) => $k < 16, ARRAY_FILTER_USE_BOTH));
        }
        sort($usedTools);

        if ($isPrusa) {
            $metadata['printer_vendor'] = 'prusa';
            $metadata['tool_count']     = max(1, count($nozzles), $usedTools ? max($usedTools) + 1 : 1);
            $metadata['tools_used']     = $usedTools ?: [0];
            // Skutečné teploty z G-code (hodnoty v konfiguraci jsou po filamentech/nástrojích)
            if ($firstBedS !== null) {
                $metadata['bed_temp'] = $firstBedS;
                $metadata['bed_temp_initial_layer'] = $firstBedS;
            }
            if ($firstNozzle !== null) {
                $metadata['nozzle_temp'] = $firstNozzle;
            }
        }

        // ---- Filamenty
        $ftRaw          = $configData['filament_type'] ?? 'PLA';
        $ftSep          = str_contains($ftRaw, ';') ? ';' : ',';
        $filamentTypes  = array_map('trim', explode($ftSep, $ftRaw));
        $colorRaw       = $configData['filament_colour'] ?? '';
        if (trim(str_replace(';', '', $colorRaw)) === '') {
            $colorRaw = $configData['extruder_colour'] ?? '';   // PrusaSlicer často jen barvy nástrojů
        }
        $filamentColors = array_map('trim', explode(';', $colorRaw));

        $filaments = [];
        if ($usedG) {
            // Spotřeba po filamentech/nástrojích je známá - jen použité
            foreach ($usedG as $i => $g) {
                if ($g <= 0) continue;
                $filaments[] = [
                    'type'            => $filamentTypes[$i] ?? ($filamentTypes[0] ?? 'PLA'),
                    'color'           => ($filamentColors[$i] ?? '') ?: '#888888',
                    'used_g'          => round($g, 2),
                    'used_m'          => isset($usedMm[$i]) ? round($usedMm[$i] / 1000, 2) : 0,
                    'tool'            => $i,
                    'extruder_index'  => $i,
                    'nozzle_diameter' => $nozzles[$i] ?? null,
                ];
            }
        }
        if (!$filaments) {
            // Původní chování (Bambu G-code bez rozpisu spotřeby)
            $filamentCount = $headerData['filament_count'] ?? 1;
            for ($fi = 0; $fi < max(1, $filamentCount); $fi++) {
                $type = $filamentTypes[$fi] ?? ($filamentTypes[0] ?? 'PLA');
                if ($type === '') continue;
                $filaments[] = [
                    'type'           => $type,
                    'color'          => ($filamentColors[$fi] ?? '') ?: '#888888',
                    'used_g'         => 0,
                    'used_m'         => 0,
                    'extruder_index' => $fi,
                ];
            }
            if ($filaments && !empty($metadata['filament_used_g'])) {
                $filaments[0]['used_g'] = $metadata['filament_used_g'];
                $filaments[0]['used_m'] = $metadata['filament_used_m'] ?? 0;
            }
        }

        if ($filaments) {
            $metadata['filament_type'] = implode(', ', array_unique(array_column($filaments, 'type')));
            $firstTool = $filaments[0]['tool'] ?? 0;
            $metadata['plates'] = [[
                'index'              => 1,
                'has_gcode'          => true,
                'total_layer_num'    => $metadata['total_layer_num'] ?? null,
                'layer_height'       => $metadata['layer_height'] ?? null,
                'nozzle_diameter'    => isset($nozzles[$firstTool]) ? (string) $nozzles[$firstTool] : $this->firstCsvValue($configData['nozzle_diameter'] ?? ''),
                'bed_type'           => $bedType,
                'filaments'          => $filaments,
                'filament_type'      => $metadata['filament_type'],
                'filament_used_g'    => $metadata['filament_used_g'] ?? null,
                'filament_used_m'    => $metadata['filament_used_m'] ?? null,
                'print_time_seconds' => $metadata['print_time_seconds'] ?? null,
            ]];
        }

        $metadata['plate_count'] = 1;

        return $this->finish($metadata);
    }

    // =====================================================================
    //  Binární G-code (.bgcode, PrusaSlicer)
    // =====================================================================

    /**
     * Formát: "GCDE", verze (u32), typ kontrolního součtu (u16), pak bloky:
     *   typ (u16), komprese (u16), nezkomprimovaná délka (u32),
     *   [zkomprimovaná délka (u32) pokud komprese != 0],
     *   parametry (2 B; u náhledu 6 B: formát, šířka, výška), data, [CRC32].
     * Typy: 0 file meta, 1 gcode, 2 slicer meta, 3 printer meta, 4 print meta, 5 náhled.
     * Komprese: 0 žádná, 1 deflate (zlib), 2/3 heatshrink (jen G-code - nepotřebujeme).
     * Formát náhledu: 0 PNG, 1 JPG, 2 QOI.
     */
    private function parseBinary(string $filePath): array
    {
        $data = @file_get_contents($filePath);
        if ($data === false || strlen($data) < 10) {
            return [];
        }

        $checksumType = unpack('v', $data, 8)[1];
        $offset = 10;
        $len    = strlen($data);

        $ini        = [];   // printer + print + file metadata (klíč=hodnota)
        $slicerIni  = [];
        $slicerJson = null;
        $bestThumb  = null;
        $bestArea   = 0;

        while ($offset + 8 <= $len) {
            ['t' => $type, 'c' => $comp, 'u' => $usize] = unpack('vt/vc/Vu', $data, $offset);
            $offset += 8;
            $csize = $usize;
            if ($comp !== 0) {
                $csize = unpack('V', $data, $offset)[1];
                $offset += 4;
            }
            $paramLen = $type === 5 ? 6 : 2;
            $params   = substr($data, $offset, $paramLen);
            $offset  += $paramLen;
            $payload  = substr($data, $offset, $csize);
            $offset  += $csize;
            if ($checksumType === 1) {
                $offset += 4;
            }

            if ($type === 1) {
                continue; // samotný G-code
            }

            if ($type === 5) {
                ['f' => $fmt, 'w' => $w, 'h' => $h] = unpack('vf/vw/vh', $params);
                if ($fmt === 0 && $comp === 0 && $w * $h >= $bestArea) {   // PNG
                    $bestThumb = $payload;
                    $bestArea  = $w * $h;
                }
                continue;
            }

            if ($comp === 1) {
                $payload = @gzuncompress($payload);
                if ($payload === false) continue;
            } elseif ($comp !== 0) {
                continue;
            }

            if ($type === 2 && str_starts_with(ltrim($payload), '{')) {
                $slicerJson = json_decode($payload, true);
                continue;
            }

            $kv = $this->parseIni($payload);
            if ($type === 2) {
                $slicerIni = $kv + $slicerIni;
            } else {
                $ini = $kv + $ini;
            }
        }

        $metadata = ['has_gcode' => true, 'plate_count' => 1, 'printer_vendor' => 'prusa'];

        if ($bestThumb) {
            $metadata['thumbnail_data'] = base64_encode($bestThumb);
        }

        $legacyModel = $ini['printer_model'] ?? ($slicerIni['printer_model'] ?? null);
        $metadata['printer_model'] = $this->friendlyPrusaModel($legacyModel)
            ?? ($slicerJson['config']['printer']['config_name'] ?? null);
        $metadata['printer_model_code'] = $legacyModel;

        $timeStr = $ini['estimated printing time (normal mode)'] ?? null;
        if ($timeStr) {
            $metadata['print_time']         = $timeStr;
            $metadata['print_time_seconds'] = $this->parseTime($timeStr);
        }

        $usedG  = $this->csvFloats($ini['filament used [g]'] ?? '');
        $usedMm = $this->csvFloats($ini['filament used [mm]'] ?? '');
        if ($usedG) {
            $metadata['filament_used_g'] = round(array_sum($usedG), 2);
        }
        if ($usedMm) {
            $metadata['filament_used_m'] = round(array_sum($usedMm) / 1000, 2);
        }

        $nozzles   = $this->csvFloats($ini['nozzle_diameter'] ?? ($slicerIni['nozzle_diameter'] ?? ''));
        $types     = array_map('trim', explode(';', $ini['filament_type'] ?? ($slicerIni['filament_type'] ?? 'PLA')));
        $colorsRaw = $slicerIni['filament_colour'] ?? '';
        if (trim(str_replace(';', '', $colorsRaw)) === '') {
            $colorsRaw = $ini['extruder_colour'] ?? ($slicerIni['extruder_colour'] ?? '');
        }
        $colors = array_map('trim', explode(';', $colorsRaw));
        $temps  = $this->csvFloats($ini['temperature'] ?? ($slicerIni['temperature'] ?? ''));
        $beds   = $this->csvFloats($ini['bed_temperature'] ?? ($slicerIni['bed_temperature'] ?? ''));

        $usedTools = [];
        foreach ($usedG as $i => $g) {
            if ($g > 0) $usedTools[] = $i;
        }
        $first = $usedTools[0] ?? 0;

        $toolCount = (int) ($slicerJson['config']['printer']['tool_count'] ?? 0);
        $metadata['tool_count']   = max(1, $toolCount, count($nozzles));
        $metadata['tools_used']   = $usedTools ?: [0];
        $metadata['layer_height'] = $ini['layer_height'] ?? ($slicerIni['layer_height'] ?? null);
        $metadata['initial_layer_height'] = $slicerIni['first_layer_height'] ?? null;
        $metadata['nozzle_temp']  = isset($temps[$first]) ? (int) $temps[$first] : null;
        $metadata['bed_temp']     = isset($beds[$first]) ? (int) $beds[$first] : null;
        $metadata['bed_temp_initial_layer'] = $metadata['bed_temp'];
        $metadata['bed_type']     = $slicerJson['config']['printer']['sheet']['name'] ?? null;
        $metadata['infill_density'] = $slicerIni['fill_density'] ?? null;
        $metadata['infill_pattern'] = $slicerIni['fill_pattern'] ?? null;
        $metadata['wall_loops']     = $slicerIni['perimeters'] ?? null;
        $metadata['print_profile']  = $slicerIni['print_settings_id'] ?? null;

        $filaments = [];
        foreach ($usedTools as $i) {
            $filaments[] = [
                'type'            => $types[$i] ?? ($types[0] ?? 'PLA'),
                'color'           => ($colors[$i] ?? '') ?: '#888888',
                'used_g'          => round($usedG[$i], 2),
                'used_m'          => isset($usedMm[$i]) ? round($usedMm[$i] / 1000, 2) : 0,
                'tool'            => $i,
                'extruder_index'  => $i,
                'nozzle_diameter' => $nozzles[$i] ?? null,
            ];
        }

        if ($filaments) {
            $metadata['filament_type'] = implode(', ', array_unique(array_column($filaments, 'type')));
            $metadata['plates'] = [[
                'index'              => 1,
                'has_gcode'          => true,
                'layer_height'       => $metadata['layer_height'],
                'nozzle_diameter'    => isset($nozzles[$first]) ? (string) $nozzles[$first] : null,
                'bed_type'           => $metadata['bed_type'],
                'filaments'          => $filaments,
                'filament_type'      => $metadata['filament_type'],
                'filament_used_g'    => $metadata['filament_used_g'] ?? null,
                'filament_used_m'    => $metadata['filament_used_m'] ?? null,
                'print_time_seconds' => $metadata['print_time_seconds'] ?? null,
            ]];
        }

        return $this->finish($metadata);
    }

    // =====================================================================
    //  Pomocné
    // =====================================================================

    private function finish(array $metadata): array
    {
        return array_filter($metadata, fn ($v) => $v !== null && $v !== '' && $v !== false);
    }

    private function parseIni(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $text) as $line) {
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $out[trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1));
        }
        return $out;
    }

    /** "0.00, 7.24, 0" / "0.4,0.4" -> [0.0, 7.24, 0.0] */
    private function csvFloats(string $csv): array
    {
        if (trim($csv) === '') return [];
        return array_map(fn ($v) => (float) trim($v), preg_split('/[,;]/', $csv));
    }

    private function isPrusaModel(string $model): bool
    {
        return (bool) preg_match('/prusa|^xl|^mk[34]|^mini|core ?one|^c1/i', trim($model));
    }

    /** XL5IS -> "Prusa XL 5T", MK4S -> "Prusa MK4S" apod. */
    private function friendlyPrusaModel(?string $code): ?string
    {
        if (!$code) return null;
        if (preg_match('/^XL(\d)?/i', $code, $m)) {
            return 'Prusa XL' . (!empty($m[1]) && $m[1] !== '1' ? " {$m[1]}T" : '');
        }
        if (preg_match('/^COREONE(L)?/i', $code, $m)) {
            return 'Prusa CORE One' . (!empty($m[1]) ? ' L' : '');
        }
        return 'Prusa ' . preg_replace('/IS$/', '', strtoupper($code));
    }

    private function firstCsvValue(string $csv): string
    {
        $parts = explode(',', str_replace(';', ',', $csv));
        return trim($parts[0] ?? '');
    }

    private function parseTime(string $time): int
    {
        $seconds = 0;
        if (preg_match('/(\d+)d/', $time, $m)) $seconds += (int) $m[1] * 86400;
        if (preg_match('/(\d+)h/', $time, $m)) $seconds += (int) $m[1] * 3600;
        if (preg_match('/(\d+)m(?!s)/', $time, $m)) $seconds += (int) $m[1] * 60;
        if (preg_match('/(\d+)s/', $time, $m)) $seconds += (int) $m[1];
        return $seconds;
    }
}
