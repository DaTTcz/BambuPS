<?php

namespace App\Services;

class GcodeParserService
{
    public function parse(string $filePath): array
    {
        $metadata      = [];
        $headerData    = [];
        $configData    = [];
        $handle        = fopen($filePath, 'r');
        if (!$handle) return [];

        $inHeaderBlock = false;
        $inConfigBlock = false;
        $inThumbnail   = false;
        $thumbnailData = '';

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line);

            // Header block
            if ($line === '; HEADER_BLOCK_START') { $inHeaderBlock = true; continue; }
            if ($line === '; HEADER_BLOCK_END')   { $inHeaderBlock = false; continue; }

            // Config block
            if ($line === '; CONFIG_BLOCK_START') { $inConfigBlock = true; continue; }
            if ($line === '; CONFIG_BLOCK_END')   { $inConfigBlock = false; continue; }

            // Thumbnail
            if (str_starts_with($line, '; thumbnail begin')) {
                $inThumbnail   = true;
                $thumbnailData = '';
                continue;
            }
            if ($line === '; thumbnail end') {
                $inThumbnail = false;
                if ($thumbnailData) {
                    $metadata['thumbnail_data'] = $thumbnailData;
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
                    $metadata['total_layer_num'] = (int)$m[1];
                }
                if (preg_match('/max_z_height:\s*([\d.]+)/', $content, $m)) {
                    $metadata['max_z_height'] = (float)$m[1];
                }
                if (preg_match('/^filament:\s*(\d+)/', $content, $m)) {
                    $headerData['filament_count'] = (int)$m[1];
                }
            }

            // Config data
            if ($inConfigBlock && str_starts_with($line, '; ')) {
                $content = substr($line, 2);
                if (preg_match('/^([a-z_]+)\s*=\s*(.+)$/', $content, $m)) {
                    $configData[trim($m[1])] = trim($m[2]);
                }
            }

            // Filament spotřeba – na konci souboru mimo bloky
            if (!$inHeaderBlock && !$inConfigBlock && !$inThumbnail) {
                if (str_starts_with($line, '; filament used [g]')) {
                    if (preg_match('/=\s*(.+)$/', $line, $m)) {
                        $vals = array_map('floatval', array_map('trim', explode(',', $m[1])));
                        $metadata['filament_used_g'] = round(array_sum($vals), 2);
                    }
                }
                if (str_starts_with($line, '; filament used [mm]')) {
                    if (preg_match('/=\s*(.+)$/', $line, $m)) {
                        $vals = array_map('floatval', array_map('trim', explode(',', $m[1])));
                        $metadata['filament_used_m'] = round(array_sum($vals) / 1000, 2);
                    }
                }
            }
        }

        fclose($handle);

        // Z config dat vytáhneme důležité hodnoty
        if (!empty($configData)) {
            $metadata['printer_model']        = $configData['printer_model'] ?? null;
            $metadata['layer_height']         = $configData['layer_height'] ?? null;
            $metadata['initial_layer_height'] = $configData['initial_layer_print_height'] ?? null;
            $metadata['nozzle_temp']          = $this->firstCsvValue($configData['nozzle_temperature'] ?? '');
            $metadata['support_type']         = $configData['support_type'] ?? null;
            $metadata['spiral_mode']          = ($configData['spiral_mode'] ?? '0') === '1';
            $metadata['infill_density']       = $configData['sparse_infill_density'] ?? null;
            $metadata['infill_pattern']       = $configData['sparse_infill_pattern'] ?? null;
            $metadata['wall_loops']           = $configData['wall_loops'] ?? null;
            $metadata['print_profile']        = $configData['default_print_profile'] ?? null;
            $metadata['has_gcode']            = true;

            // Teplota podložky - pevná mapa (viz ThreeMfParserService pro
            // vysvětlení, proč nejde spolehlivě "uhodnout" název klíče)
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
            } else {
                $fallbackKey = strtolower(trim($bedType));
                $fallbackKey = preg_replace('/[^a-z0-9]+/', '_', $fallbackKey);
                $fallbackKey = trim($fallbackKey, '_') . '_temp';
                $metadata['bed_temp'] = $this->firstCsvValue($configData[$fallbackKey] ?? '');
                $metadata['bed_temp_initial_layer'] = !empty($configData[$fallbackKey . '_initial_layer'])
                    ? $this->firstCsvValue($configData[$fallbackKey . '_initial_layer'])
                    : $metadata['bed_temp'];
            }

            // filament_type může být oddělený středníkem nebo čárkou
            $ftRaw          = $configData['filament_type'] ?? 'PLA';
            $ftSep          = str_contains($ftRaw, ';') ? ';' : ',';
            $filamentTypes  = array_map('trim', explode($ftSep, $ftRaw));
            $filamentColors = array_map('trim', explode(';', $configData['filament_colour'] ?? ''));
            $filamentCount  = $headerData['filament_count'] ?? 1;

            $filaments = [];
            for ($fi = 0; $fi < max(1, $filamentCount); $fi++) {
                $type  = $filamentTypes[$fi]  ?? ($filamentTypes[0] ?? 'PLA');
                $color = $filamentColors[$fi] ?? '#888888';
                if ($type !== '') {
                    $filaments[] = [
                        'type'   => $type,
                        'color'  => $color ?: '#888888',
                        'used_g' => 0,
                        'used_m' => 0,
                    ];
                }
            }

            // Přiřadíme spotřebu filamentu
            if (!empty($filaments) && !empty($metadata['filament_used_g'])) {
                $filaments[0]['used_g'] = $metadata['filament_used_g'];
                $filaments[0]['used_m'] = $metadata['filament_used_m'] ?? 0;
            }

            if (!empty($filaments)) {
                $metadata['filament_type'] = implode(', ', array_unique(array_column($filaments, 'type')));
                $metadata['plates']        = [[
                    'index'              => 1,
                    'has_gcode'          => true,
                    'total_layer_num'    => $metadata['total_layer_num'] ?? null,
                    'layer_height'       => $metadata['layer_height'] ?? null,
                    'nozzle_diameter'    => $this->firstCsvValue($configData['nozzle_diameter'] ?? ''),
                    'bed_type'           => $bedType,
                    'filaments'          => $filaments,
                    'filament_type'      => $metadata['filament_type'],
                    'filament_used_g'    => $metadata['filament_used_g'] ?? null,
                    'filament_used_m'    => $metadata['filament_used_m'] ?? null,
                    'print_time_seconds' => $metadata['print_time_seconds'] ?? null,
                ]];
            }

            $metadata['plate_count'] = 1;
        }

        return array_filter($metadata, fn($v) => $v !== null && $v !== '' && $v !== false);
    }

    private function firstCsvValue(string $csv): string
    {
        $parts = explode(',', $csv);
        return trim($parts[0] ?? '');
    }

    private function parseTime(string $time): int
    {
        $seconds = 0;
        if (preg_match('/(\d+)h/', $time, $m)) $seconds += (int)$m[1] * 3600;
        if (preg_match('/(\d+)m/', $time, $m)) $seconds += (int)$m[1] * 60;
        if (preg_match('/(\d+)s/', $time, $m)) $seconds += (int)$m[1];
        return $seconds;
    }
}
