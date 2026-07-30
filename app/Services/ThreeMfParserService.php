<?php

namespace App\Services;

use ZipArchive;

class ThreeMfParserService
{
    public function parse(string $filePath): array
    {
        $zip = new ZipArchive();

        if ($zip->open($filePath) !== true) {
            return [];
        }

        $metadata = [];
        $plates   = [];

        // Zjistíme počet podložek
        $plateCount = 0;
        for ($i = 1; $i <= 36; $i++) {
            if ($zip->locateName("Metadata/plate_{$i}.json") !== false) {
                $plateCount = $i;
            }
        }
        if ($plateCount === 0) $plateCount = 1;

        // Náhled první podložky
        $thumbnail = $zip->getFromName('Metadata/plate_1.png');
        if ($thumbnail !== false) {
            $metadata['thumbnail_data'] = base64_encode($thumbnail);
        }

        // Parsujeme slice_info.config
        $sliceInfo = $zip->getFromName('Metadata/slice_info.config');
        if ($sliceInfo !== false) {
            $xml = @simplexml_load_string($sliceInfo);
            if ($xml && isset($xml->plate)) {
                foreach ($xml->plate as $plate) {
                    $plateData = [];

                    foreach ($plate->metadata as $item) {
                        $key   = (string) $item['key'];
                        $value = (string) $item['value'];

                        if ($key === 'index')            $plateData['index']              = (int) $value;
                        if ($key === 'prediction')       $plateData['print_time_seconds'] = (int) $value;
                        if ($key === 'weight')           $plateData['weight_g']           = (float) $value;
                        if ($key === 'support_used')     $plateData['support_used']       = $value === 'true';
                        if ($key === 'nozzle_diameters') $plateData['nozzle_diameter']    = $value;
                    }

		    // Celkový počet vrstev z layer_filament_lists – bereme maximum přes všechny filamenty
                    if (isset($plate->layer_filament_lists->layer_filament_list)) {
                        $maxLayer = 0;
                        foreach ($plate->layer_filament_lists->layer_filament_list as $lfl) {
                            $layerRanges = (string)($lfl['layer_ranges'] ?? '');
                            if ($layerRanges) {
                                $parts = explode(' ', trim($layerRanges));
                                $last = (int)end($parts);
                                if ($last > $maxLayer) $maxLayer = $last;
                            }
                        }
                        if ($maxLayer > 0) {
                            $plateData['total_layer_num'] = $maxLayer + 1;
                        }
                    }

                    // Filamenty
                    $filaments = [];
                    foreach ($plate->filament as $filament) {
                        $filaments[] = [
                            'type'           => (string) $filament['type'],
                            'color'          => (string) $filament['color'],
                            'used_g'         => (float)  $filament['used_g'],
                            'used_m'         => (float)  $filament['used_m'],
                            // "id" v XML je 1-indexované, ale gcode (M620 SxA)
                            // i ams_mapping používají 0-indexovaný extruder
                            // index - proto -1. Bez tohohle appka neví, na
                            // kterou POZICI v ams_mapping poli má zvolený
                            // AMS slot patřit, a tisk s AMS se zasekne na
                            // "čekám na filament", protože gcode žádá jiný
                            // index, než appka namapovala.
                            'extruder_index' => ((int) $filament['id']) - 1,
                        ];
                    }
                    if (!empty($filaments)) {
                        $plateData['filaments']       = $filaments;
                        $plateData['filament_used_g'] = array_sum(array_column($filaments, 'used_g'));
                        $plateData['filament_used_m'] = array_sum(array_column($filaments, 'used_m'));
                        $plateData['filament_type']   = implode(', ', array_unique(array_column($filaments, 'type')));
                    }

                    // Objekty
                    $objects = [];
                    foreach ($plate->object as $obj) {
                        if ((string)$obj['skipped'] !== 'true') {
                            $objects[] = (string) $obj['name'];
                        }
                    }
                    if (!empty($objects)) {
                        $plateData['objects'] = $objects;
                    }

                    $idx = $plateData['index'] ?? count($plates) + 1;

                    // Layer height + bed_type z plate_N.json
                    $plateJson = $zip->getFromName("Metadata/plate_{$idx}.json");
                    if ($plateJson) {
                        $pData = @json_decode($plateJson, true);
                        if ($pData) {
                            $layerH = $pData['bbox_objects'][0]['layer_height'] ?? null;
                            if ($layerH) {
                                $plateData['layer_height'] = round((float)$layerH, 2);
                            }
                            $plateData['bed_type'] = $pData['bed_type'] ?? null;
                        }
                    }

                    // Thumbnail
                    $plateThumb = $zip->getFromName("Metadata/plate_{$idx}.png");
                    if ($plateThumb !== false) {
                        $plateData['thumbnail_data'] = base64_encode($plateThumb);
                    }

                    // Má gcode?
                    $plateData['has_gcode'] = $zip->locateName("Metadata/plate_{$idx}.gcode") !== false;

                    $plates[] = $plateData;
                }
            }
        }

        // Pokud nemáme žádné plates z slice_info
        if (empty($plates)) {
            for ($i = 1; $i <= $plateCount; $i++) {
                $plateJson = $zip->getFromName("Metadata/plate_{$i}.json");
                if ($plateJson) {
                    $pData = @json_decode($plateJson, true);
                    if ($pData) {
                        $plates[] = [
                            'index'          => $i,
                            'bed_type'       => $pData['bed_type'] ?? null,
                            'nozzle_diameter'=> $pData['nozzle_diameter'] ?? null,
                            'layer_height'   => round($pData['bbox_objects'][0]['layer_height'] ?? 0, 2) ?: null,
                            'has_gcode'      => false,
                        ];
                    }
                }
            }
        }

        // Souhrnné hodnoty
        if (!empty($plates)) {
            $metadata['plates']      = $plates;
            $metadata['plate_count'] = count($plates);

            $totalSeconds = array_sum(array_column($plates, 'print_time_seconds'));
            $totalGrams   = array_sum(array_column($plates, 'filament_used_g'));
            $totalMeters  = array_sum(array_column($plates, 'filament_used_m'));

            if ($totalSeconds > 0) {
                $metadata['print_time_seconds'] = $totalSeconds;
                $metadata['print_time']         = $this->formatSeconds($totalSeconds);
            }
            if ($totalGrams > 0) {
                $metadata['filament_used_g'] = round($totalGrams, 2);
                $metadata['filament_used_m'] = round($totalMeters, 2);
            }

            $allTypes = [];
            foreach ($plates as $p) {
                if (!empty($p['filament_type'])) $allTypes[] = $p['filament_type'];
            }
            if (!empty($allTypes)) {
                $metadata['filament_type'] = implode(', ', array_unique($allTypes));
            }

            $metadata['has_gcode'] = !empty(array_filter(array_column($plates, 'has_gcode')));

            if (isset($plates[0]['support_used'])) {
                $metadata['support_used'] = $plates[0]['support_used'];
            }
        }

	// Project settings – printer model + tiskové parametry
        $projectSettings = $zip->getFromName('Metadata/project_settings.config');
        if ($projectSettings !== false) {
            $json = @json_decode($projectSettings, true);
            if ($json) {
                $metadata['printer_model']        = $json['printer_model'] ?? null;
                $metadata['layer_height']         = $json['layer_height'] ?? null;
                $metadata['initial_layer_height'] = $json['initial_layer_print_height'] ?? null;
                $metadata['nozzle_temp']          = $json['nozzle_temperature'][0] ?? null;
                $metadata['support_type']         = $json['support_type'] ?? null;
                $metadata['spiral_mode']          = ($json['spiral_mode'] ?? '0') === '1';
                $metadata['infill_density']       = $json['sparse_infill_density'] ?? null;
                $metadata['infill_pattern']       = $json['sparse_infill_pattern'] ?? null;
                $metadata['wall_loops']           = $json['wall_loops'] ?? null;
                $metadata['print_profile']        = $json['default_print_profile'] ?? null;

		// Teplota podložky – podle curr_bed_type
                $bedType    = $json['curr_bed_type'] ?? '';
                $metadata['bed_type'] = $bedType;

                // Aliasy pro ROZPOZNÁNÍ curr_bed_type ze souboru (víc
                // možných řetězců může mířit na stejný skutečný klíč -
                // "High Temp Plate" a "Hot Plate" jsou stejná podložka).
                // Nejde je spolehlivě "uhodnout" z názvu - dřívější
                // dynamické hádání mělo bug (rtrim($str, '_temp') maže
                // znaky z množiny, ne podřetězec), což omylem vybíralo
                // "textured_cool_plate_temp" (40°C) místo správného
                // "textured_plate_temp" (100°C).
                $bedTypeAliases = [
                    'cool plate (supertack)'              => 'supertack_plate_temp',
                    'cool plate'                           => 'cool_plate_temp',
                    'engineering plate'                    => 'eng_plate_temp',
                    'smooth pei plate / high temp plate'   => 'hot_plate_temp',
                    'high temp plate'                      => 'hot_plate_temp',
                    'hot plate'                             => 'hot_plate_temp',
                    'textured pei plate'                   => 'textured_plate_temp',
                    'textured cool plate'                  => 'textured_cool_plate_temp',
                ];

                $bedKey = $bedTypeAliases[strtolower(trim($bedType))] ?? null;

                if ($bedKey && isset($json[$bedKey][0])) {
                    $metadata['bed_temp'] = $json[$bedKey][0];
                    // Teplota podložky pro PRVNÍ vrstvu - stejný princip
                    // jako u výšky vrstvy (bed_temp = ustálená hodnota pro
                    // zbytek tisku, bed_temp_initial_layer = jen 1. vrstva).
                    $metadata['bed_temp_initial_layer'] = $json[$bedKey . '_initial_layer'][0] ?? $metadata['bed_temp'];
                } else {
                    // Neznámý/nový typ podložky - zkusíme přímý odvozený
                    // klíč, jinak necháme prázdné (radši nic než tichá
                    // špatná hodnota).
                    $fallbackKey = strtolower(trim($bedType));
                    $fallbackKey = preg_replace('/[^a-z0-9]+/', '_', $fallbackKey);
                    $fallbackKey = trim($fallbackKey, '_') . '_temp';
                    $metadata['bed_temp'] = $json[$fallbackKey][0] ?? null;
                    $metadata['bed_temp_initial_layer'] = $json[$fallbackKey . '_initial_layer'][0] ?? $metadata['bed_temp'];
		}

                // Čistý seznam pro NABÍDKU (dropdown) - přesně jeden
                // popisek na skutečný klíč, žádné duplicitní aliasy.
                // Popisky odpovídají oficiálnímu pojmenování v Bambu Studiu.
                $canonicalBedTypes = [
                    'Cool Plate (SuperTack)'              => 'supertack_plate_temp',
                    'Cool Plate'                           => 'cool_plate_temp',
                    'Textured Cool Plate'                  => 'textured_cool_plate_temp',
                    'Engineering Plate'                    => 'eng_plate_temp',
                    'Smooth PEI Plate / High Temp Plate'   => 'hot_plate_temp',
                    'Textured PEI Plate'                   => 'textured_plate_temp',
                ];

                $bedTempOptions = [];
                $canonicalLabelForCurrent = null;
                foreach ($canonicalBedTypes as $label => $key) {
                    if (isset($json[$key][0])) {
                        $bedTempOptions[$label] = [
                            'initial' => (int) $json[$key][0],
                            'other'   => (int) ($json[$key . '_initial_layer'][0] ?? $json[$key][0]),
                        ];
                    }
                    // Zjistíme, který kanonický popisek odpovídá souborem
                    // zvolené podložce ($bedKey), i když curr_bed_type byl
                    // alias (např. "Hot Plate" místo "Smooth PEI Plate /
                    // High Temp Plate") - appka pak umí správně předvybrat
                    // odpovídající položku v dropdownu.
                    if ($bedKey && $key === $bedKey) {
                        $canonicalLabelForCurrent = $label;
                    }
                }
                $metadata['bed_type_canonical'] = $canonicalLabelForCurrent ?? $bedType;
                $metadata['bed_temp_options'] = $bedTempOptions;
	    }
	}

        $zip->close();

        return array_filter($metadata, fn($v) => $v !== null && $v !== '');
    }

    public function extractThumbnail(string $filePath, string $destinationPath): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) return false;

        $thumbnail = $zip->getFromName('Metadata/plate_1.png');
        $zip->close();

        if ($thumbnail === false) return false;

        file_put_contents($destinationPath, $thumbnail);
        return true;
    }

    private function formatSeconds(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        if ($h > 0) return sprintf('%dh %02dm', $h, $m);
        return sprintf('%dm %02ds', $m, $seconds % 60);
    }
}
