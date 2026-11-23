<?php

namespace App\Livewire;

use App\Models\File;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Services\PrintHistoryService;
use Livewire\Component;

class FileDetailPage extends Component
{
    public File $file;
    public int $selectedPlate = 1;

    // Dialog tisku
    public bool $showPrintDialog = false;
    public ?int $printPrinterId  = null;
    public array $amsMapping     = [];
    public ?int $selectingAmsForFilament = null;
    public ?int $selectingAmsUnit        = null;
    public bool $useBedLeveling   = true;
    /** Vyrovnání podložky: auto (podle BedMeshService) | always | never */
    public string $bedLevelingMode = 'auto';
    public bool $useTimelapse     = false;
    public bool $useAms           = true;
    public bool $useLayerInspect  = false;
    public bool $useFlowCali      = false;
    public bool $useVibrationCali = false;
    public string $bedType        = 'auto';
    /** ID objektů desky, které se nemají tisknout */
    public array $skipObjects     = [];
    // Vybraný typ FYZICKÉ podložky pro patch teploty v gcode - nesouvisí
    // s $bedType výše (to je MQTT pole, které má zůstat "auto").
    public string $selectedBedPlate = '';

    /** Obsahuje gcode zvolené desky skenování 1. vrstvy? null = nevíme */
    public ?bool $fileHasLayerScan = null;

    /** Teplota trysky po filamentech: [i => ['initial', 'other', 'new_initial', 'new_other']] */
    public array $nozzleTemps = [];

    // Rychlé akce (stejné jako v přehledu souborů)
    public bool $isReparsing      = false;
    public bool $showRenameModal  = false;
    public string $renameValue    = '';
    public bool $showMoveModal    = false;
    public bool $showDeleteModal  = false;

    public function mount(File $file): void
    {
        // Metadata ze starší verze parseru -> potichu načíst znovu
        if ((int) ($file->metadata['parser_version'] ?? 0) < \App\Services\ThreeMfParserService::VERSION) {
            try {
                (new \App\Services\FileReparseService())->reparse($file);
                $file->refresh();
            } catch (\Throwable $e) {
                \Log::warning("Automatické znovunačtení souboru {$file->id} selhalo: " . $e->getMessage());
            }
        }

        $this->file = $file;
        $plates = $file->metadata['plates'] ?? [];
        if (!empty($plates)) {
            $this->selectedPlate = $plates[0]['index'] ?? 1;
        }

        // Odkaz "Tisknout znovu" z historie tisku (?reprint=ID)
        if ($jobId = request()->integer('reprint')) {
            $this->reprint($jobId);
        }
    }

    /**
     * Otevře dialog tisku se stejným nastavením jako dřívější tisk
     * (tiskárna, deska, podložka, teploty trysky, AMS sloty, volby tisku).
     * Nic se neodešle - uživatel nastavení zkontroluje a potvrdí.
     */
    public function reprint(int $jobId): void
    {
        $job = \App\Models\PrintJob::where('id', $jobId)->where('file_id', $this->file->id)->first();
        if (!$job) {
            $this->dispatch('toast', type: 'error', message: 'Tisk v historii nebyl nalezen.');
            return;
        }
        $printer = $job->printer_id ? Printer::where('id', $job->printer_id)->where('enabled', true)->first() : null;
        if (!$printer) {
            $this->dispatch('toast', type: 'error', message: 'Tiskárna z tohoto tisku už není dostupná – vyber ji ručně.');
            return;
        }

        $plates = collect($this->file->metadata['plates'] ?? []);
        if ($job->plate_index && $plates->contains('index', $job->plate_index)) {
            $this->selectedPlate = $job->plate_index;
        }

        $this->openPrintDialog($printer->id);

        $o = $job->options ?? [];

        // Podložka
        $plate = $o['bed_plate'] ?? $job->bed_type;
        $known = array_keys($this->file->metadata['bed_temp_options'] ?? []);
        if ($plate && ($plate === $this->selectedBedPlate || in_array($plate, $known, true))) {
            $this->selectedBedPlate = $plate;
        }

        // Volby tisku
        if (in_array($o['bed_leveling_mode'] ?? null, ['auto', 'always', 'never'], true)) {
            $this->bedLevelingMode = $o['bed_leveling_mode'];
        } elseif (array_key_exists('bed_leveling', $o)) {
            $this->bedLevelingMode = $o['bed_leveling'] ? 'always' : 'never';
        }
        foreach ([
            'timelapse'      => 'useTimelapse',
            'flow_cali'      => 'useFlowCali',
            'layer_inspect'  => 'useLayerInspect',
            'vibration_cali' => 'useVibrationCali',
            'use_ams'        => 'useAms',
        ] as $key => $prop) {
            if (array_key_exists($key, $o)) {
                $this->{$prop} = (bool) $o[$key];
            }
        }
        if ($this->fileHasLayerScan === false) {
            $this->useLayerInspect = false;
        }

        // Teploty trysky - jen když soubor má pořád stejné výchozí teploty
        foreach ($o['nozzle'] ?? [] as $i => $t) {
            if (isset($this->nozzleTemps[$i])
                && (int) $this->nozzleTemps[$i]['initial'] === (int) ($t['initial'] ?? -1)
                && (int) $this->nozzleTemps[$i]['other'] === (int) ($t['other'] ?? -1)) {
                $this->nozzleTemps[$i]['new_initial'] = (int) ($t['new_initial'] ?? $t['initial']);
                $this->nozzleTemps[$i]['new_other']   = (int) ($t['new_other'] ?? $t['other']);
            }
        }

        // Vynechané objekty - jen ty, které deska pořád má
        $known = array_column($this->plateObjects, 'id');
        $this->skipObjects = array_values(array_intersect(array_map('intval', $o['skip_objects'] ?? []), $known));
        if (count($this->skipObjects) >= count($known)) {
            $this->skipObjects = [];
        }

        // AMS sloty - jen ty, ve kterých je pořád nějaký filament
        $loaded = [];
        foreach ($printer->status['ams']['ams'] ?? [] as $unit) {
            foreach ($unit['tray'] ?? [] as $tray) {
                if (($tray['tray_color'] ?? '00000000') !== '00000000') {
                    $loaded[(int) $unit['id'] . '-' . (int) $tray['id']] = true;
                }
            }
        }
        foreach ($o['ams'] ?? [] as $i => $m) {
            if (isset($this->amsMapping[$i], $loaded[(int) $m['ams'] . '-' . (int) $m['slot']])) {
                $this->amsMapping[$i]['ams']  = (int) $m['ams'];
                $this->amsMapping[$i]['slot'] = (int) $m['slot'];
            }
        }

        $when = $job->started_at?->local()->format('d.m.Y H:i') ?? '';
        $this->dispatch('toast', type: 'success', message: "Nastavení převzato z tisku {$when} – zkontroluj a spusť.");
    }

    public function selectPlate(int $index): void
    {
        $this->selectedPlate = $index;
    }

    public function openPrintDialog(int $printerId): void
    {
        $this->printPrinterId          = $printerId;
        $this->amsMapping              = [];
        $this->skipObjects             = [];
        $this->selectingAmsForFilament = null;
        $this->selectingAmsUnit        = null;

        $plates       = $this->file->metadata['plates'] ?? [];
        $currentPlate = collect($plates)->firstWhere('index', $this->selectedPlate);
        $filaments    = $currentPlate['filaments'] ?? [];

        $printer  = Printer::findOrFail($printerId);
        $amsUnits = $printer->status['ams']['ams'] ?? [];

        // Sestavíme seznam všech dostupných slotů
        $availableSlots = [];
        foreach ($amsUnits as $amsUnit) {
            foreach ($amsUnit['tray'] as $tray) {
                $trayColor = $tray['tray_color'] ?? '00000000';
                if ($trayColor !== '00000000') {
                    $availableSlots[] = [
                        'ams'   => (int) $amsUnit['id'],
                        'slot'  => (int) $tray['id'],
                        'color' => strtoupper(substr($trayColor, 0, 6)),
                        'type'  => strtoupper(trim($tray['tray_type'] ?? '')),
                    ];
                }
            }
        }

        // Mapujeme filamenty na AMS sloty podle materiálu + podobnosti barvy
        foreach ($filaments as $i => $filament) {
            $filamentColor = strtoupper(substr(ltrim($filament['color'], '#'), 0, 6));
            $filamentType  = strtoupper(trim($filament['type']));

            $bestSlot  = null;
            $bestScore = PHP_INT_MAX;

            foreach ($availableSlots as $slot) {
                $typeMatch = ($slot['type'] === $filamentType || $slot['type'] === '');
                $typeBonus = $typeMatch ? 0 : 1000;
                $score     = $this->colorDistance($filamentColor, $slot['color']) + $typeBonus;
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestSlot  = $slot;
                }
            }

            $hasMatchingMaterial = collect($availableSlots)->contains(
                fn($s) => $s['type'] === $filamentType || $s['type'] === ''
            );

            $this->amsMapping[$i] = [
                'ams'              => $bestSlot['ams']  ?? 0,
                'slot'             => $bestSlot['slot'] ?? 0,
                'tray_color'       => $filamentColor . 'FF',
                'tray_type'        => $filament['type'],
                'material_warning' => !$hasMatchingMaterial,
                // Pozice v ams_mapping poli, na kterou gcode (M620 SxA)
                // skutečně odkazuje - NENÍ totéž co zvolený AMS slot!
                // Bez tohohle appka posílala hodnotu na špatnou pozici a
                // firmware čekal na materiál, který nikdy nedostal.
                'extruder_index'   => $filament['extruder_index'] ?? $i,
            ];
        }

        // Načteme uložené předvolby
        $defaults = session('print_defaults', []);
        $this->useBedLeveling   = $defaults['useBedLeveling']   ?? true;
        $this->bedLevelingMode  = $defaults['bedLevelingMode']  ?? 'auto';
        $this->useTimelapse     = $defaults['useTimelapse']      ?? false;
        $this->useAms           = $defaults['useAms']            ?? true;
        $this->useLayerInspect  = $defaults['useLayerInspect']   ?? false;
        $this->useFlowCali      = $defaults['useFlowCali']       ?? false;
        $this->useVibrationCali = $defaults['useVibrationCali']  ?? false;
        $this->bedType          = $defaults['bedType']           ?? 'auto';
        // Kontrola 1. vrstvy má smysl jen u gcode se skenovacími příkazy
        // (slicer je vloží jen při scan_first_layer = 1).
        $this->fileHasLayerScan = (new \App\Services\GcodePatcherService())->hasFirstLayerScan(
            storage_path('app/private/' . $this->file->disk_path),
            $this->selectedPlate
        );
        if ($this->fileHasLayerScan === false) {
            $this->useLayerInspect = false;
        }

        // Teploty trysky ze souboru - v dialogu jdou před tiskem změnit
        $this->nozzleTemps = [];
        try {
            $temps = (new \App\Services\GcodePatcherService())->nozzleTemps(
                storage_path('app/private/' . $this->file->disk_path),
                $this->selectedPlate,
                $filaments
            );
            foreach ($temps as $i => $t) {
                $this->nozzleTemps[$i] = $t + ['new_initial' => $t['initial'], 'new_other' => $t['other']];
            }
        } catch (\Throwable $e) {
            \Log::warning('Teploty trysky se nepodařilo načíst: ' . $e->getMessage());
        }

        // Výchozí volba = typ podložky, pro který byl soubor naslicovaný
        $this->selectedBedPlate = $this->file->metadata['bed_type_canonical']
            ?? $this->file->metadata['bed_type']
            ?? '';

        $this->showPrintDialog = true;
    }

    private function colorDistance(string $hex1, string $hex2): int
    {
        if (strlen($hex1) < 6 || strlen($hex2) < 6) return PHP_INT_MAX;
        $r1 = hexdec(substr($hex1, 0, 2));
        $g1 = hexdec(substr($hex1, 2, 2));
        $b1 = hexdec(substr($hex1, 4, 2));
        $r2 = hexdec(substr($hex2, 0, 2));
        $g2 = hexdec(substr($hex2, 2, 2));
        $b2 = hexdec(substr($hex2, 4, 2));
        return abs($r1 - $r2) + abs($g1 - $g2) + abs($b1 - $b2);
    }

    public function selectAmsFor(int $filamentIndex, int $amsId): void
    {
        if ($this->selectingAmsForFilament === $filamentIndex && $this->selectingAmsUnit === $amsId) {
            $this->selectingAmsForFilament = null;
            $this->selectingAmsUnit        = null;
            return;
        }
        $this->selectingAmsForFilament = $filamentIndex;
        $this->selectingAmsUnit        = $amsId;
    }

    public function closePrintDialog(): void
    {
        $this->showPrintDialog = false;
        $this->printPrinterId  = null;
    }

    /** Teplota podložky (1. vrstva) pro právě zvolený typ podložky */
    private function selectedBedTemp(): ?int
    {
        $options = $this->file->metadata['bed_temp_options'] ?? [];
        $temp    = $options[$this->selectedBedPlate]['initial']
            ?? $this->file->metadata['bed_temp_initial_layer']
            ?? null;
        return $temp !== null ? (int) $temp : null;
    }

    /**
     * Doporučení chytrého vyrovnání podložky pro zvolenou tiskárnu,
     * podložku a teplotu: ['level' => bool, 'reason' => string]
     */
    public function getBedLevelingAdviceProperty(): ?array
    {
        if (!$this->printPrinterId) {
            return null;
        }
        $printer = Printer::find($this->printPrinterId);
        if (!$printer) {
            return null;
        }
        return (new \App\Services\BedMeshService())->advise(
            $printer,
            $this->selectedBedPlate ?: ($this->file->metadata['bed_type'] ?? null),
            $this->selectedBedTemp()
        );
    }

    public function saveAsDefaults(): void
    {
        session([
            'print_defaults' => [
                'useBedLeveling'   => $this->useBedLeveling,
                'bedLevelingMode'  => $this->bedLevelingMode,
                'useTimelapse'     => $this->useTimelapse,
                'useAms'           => $this->useAms,
                'useLayerInspect'  => $this->useLayerInspect,
                'useFlowCali'      => $this->useFlowCali,
                'useVibrationCali' => $this->useVibrationCali,
                'bedType'          => $this->bedType,
            ]
        ]);
        $this->dispatch('toast', type: 'success', message: 'Předvolby uloženy');
    }

    public function setAmsSlot(int $filamentIndex, int $amsId, int $slotId): void
    {
        if (isset($this->amsMapping[$filamentIndex])) {
            $this->amsMapping[$filamentIndex]['ams']  = $amsId;
            $this->amsMapping[$filamentIndex]['slot'] = $slotId;
            $this->selectingAmsForFilament = null;
            $this->selectingAmsUnit        = null;
        }
    }

    /**
     * Čitelný popis toho, co se před tiskem změnilo oproti souboru
     * (ukládá se do historie tisku).
     */
    private function describeOverrides(?array $bed): array
    {
        $out = [];

        if ($bed) {
            $out[] = sprintf('Podložka: %s (%d °C) → %s (%d °C)',
                $bed['fromPlate'] ?: '?', (int) ($bed['from']['other'] ?? 0),
                $bed['toPlate'], (int) ($bed['to']['other'] ?? 0));
        }

        $plate     = collect($this->file->metadata['plates'] ?? [])->firstWhere('index', $this->selectedPlate) ?? [];
        $filaments = array_values($plate['filaments'] ?? []);
        foreach ($this->nozzleTemps as $i => $t) {
            $parts = [];
            $newI  = (int) ($t['new_initial'] ?? 0) ?: (int) $t['initial'];
            $newO  = (int) ($t['new_other'] ?? 0) ?: (int) $t['other'];
            if ($newI !== (int) $t['initial']) {
                $parts[] = "1. vrstva {$t['initial']} → {$newI} °C";
            }
            if ($newO !== (int) $t['other']) {
                $parts[] = "další vrstvy {$t['other']} → {$newO} °C";
            }
            if ($parts) {
                $label = count($this->nozzleTemps) > 1 && isset($filaments[$i])
                    ? 'Tryska (filament ' . ($i + 1) . ' ' . ($filaments[$i]['type'] ?? '?') . ')'
                    : 'Tryska';
                $out[] = $label . ': ' . implode(', ', $parts);
            }
        }

        if ($this->skipObjects) {
            $objs  = $this->plateObjects;
            $names = [];
            foreach ($objs as $n => $o) {
                if (in_array($o['id'], $this->skipObjects, true)) {
                    $names[] = '#' . ($n + 1) . ' ' . $o['name'];
                }
            }
            $out[] = 'Vynechané objekty (' . count($names) . ' z ' . count($objs) . '): ' . implode(', ', $names);
        }

        return $out;
    }

    public function resetNozzle(int $i): void
    {
        if (isset($this->nozzleTemps[$i])) {
            $this->nozzleTemps[$i]['new_initial'] = $this->nozzleTemps[$i]['initial'];
            $this->nozzleTemps[$i]['new_other']   = $this->nozzleTemps[$i]['other'];
        }
    }

    /**
     * Změny teploty trysky pro patcher.
     * @return array|null ['first' => [°C => °C], 'rest' => [°C => °C], 'insert' => °C|null],
     *                    prázdné pole = beze změny, null = chyba (zobrazená v toastu)
     */
    private function buildNozzleMap(): ?array
    {
        $first = $rest = [];
        $insert = null;
        $changed = false;

        $add = function (array &$map, int $from, int $to): bool {
            if (isset($map[$from]) && $map[$from] !== $to) {
                $this->dispatch('toast', type: 'error',
                    message: "Víc filamentů má v souboru stejnou teplotu {$from} °C, ale chceš jim nastavit různé – v gcode je nejde rozlišit.");
                return false;
            }
            $map[$from] = $to;
            return true;
        };

        foreach ($this->nozzleTemps as $t) {
            $oldI = (int) $t['initial'];
            $oldO = (int) $t['other'];
            $newI = (int) ($t['new_initial'] ?? 0) ?: $oldI;
            $newO = (int) ($t['new_other'] ?? 0) ?: $oldO;

            foreach ([$newI, $newO] as $v) {
                if ($v < 150 || $v > 350) {
                    $this->dispatch('toast', type: 'error', message: "Teplota trysky {$v} °C je mimo rozsah 150–350 °C");
                    return null;
                }
            }
            if ($newI === $oldI && $newO === $oldO) {
                continue;
            }
            $changed = true;

            if (!$add($first, $oldI, $newI) || !$add($rest, $oldO, $newO)) {
                return null;
            }

            // Soubor má 1. i další vrstvy stejně -> slicer na 2. vrstvě
            // teplotu nenastavuje, appka tam M104 vloží
            if ($oldI === $oldO && $newI !== $newO) {
                if (count($this->nozzleTemps) > 1) {
                    $this->dispatch('toast', type: 'error',
                        message: 'U vícebarevného tisku jde 1. vrstvu nastavit jinak než ostatní jen tehdy, když ji tak má už soubor ze sliceru.');
                    return null;
                }
                $insert = $newO;
            }
        }

        return $changed ? ['first' => $first, 'rest' => $rest, 'insert' => $insert] : [];
    }

    /** Objekty aktuální desky: [['id', 'name', 'bbox'], ...] (jen u víc objektů) */
    public function getPlateObjectsProperty(): array
    {
        $plate = collect($this->file->metadata['plates'] ?? [])->firstWhere('index', $this->selectedPlate) ?? [];
        return $plate['objects_info'] ?? [];
    }

    public function toggleSkipObject(int $id): void
    {
        $ids = array_column($this->plateObjects, 'id');
        if (!in_array($id, $ids, true)) {
            return;
        }
        if (in_array($id, $this->skipObjects, true)) {
            $this->skipObjects = array_values(array_diff($this->skipObjects, [$id]));
        } elseif (count($this->skipObjects) < count($ids) - 1) {
            $this->skipObjects[] = $id;
        } else {
            $this->dispatch('toast', type: 'error', message: 'Aspoň jeden objekt se musí tisknout.');
        }
    }

    public function confirmPrint(): void
    {
        $printer   = Printer::findOrFail($this->printPrinterId);
        $localPath = storage_path('app/private/' . $this->file->disk_path);

        // Vynechané objekty - jen platná ID aktuální desky, aspoň jeden se tiskne
        $objectIds         = array_column($this->plateObjects, 'id');
        $this->skipObjects = array_values(array_intersect(array_map('intval', $this->skipObjects), $objectIds));
        if ($this->skipObjects && !$printer->supports(\App\Printers\Capability::SKIP_OBJECTS)) {
            $this->skipObjects = [];
        }
        if ($objectIds && count($this->skipObjects) >= count($objectIds)) {
            $this->dispatch('toast', type: 'error', message: 'Aspoň jeden objekt se musí tisknout.');
            return;
        }

        if (!file_exists($localPath)) {
            $this->dispatch('toast', type: 'error', message: 'Soubor nenalezen na disku');
            return;
        }

        try {
            $driver = $printer->driver();

            // Pokud uživatel zvolil JINOU fyzickou podložku, než pro
            // kterou byl soubor naslicovaný, vytvoříme dočasnou kopii s
            // přepsanou teplotou v gcode (originál na disku zůstává beze
            // změny). Appka teplotu nemůže poslat přes MQTT - je natvrdo
            // dosazená v souboru už při slicování.
            $uploadPath  = $localPath;
            $patchedPath = null;
            $originalBedType = $this->file->metadata['bed_type_canonical']
                ?? $this->file->metadata['bed_type']
                ?? '';

            $bed = null;
            if ($this->selectedBedPlate && $this->selectedBedPlate !== $originalBedType) {
                $options = $this->file->metadata['bed_temp_options'] ?? [];
                $to      = $options[$this->selectedBedPlate] ?? null;
                // Teploty původní podložky - z options, jinak z metadat souboru
                $from    = $options[$originalBedType] ?? [
                    'initial' => (int) ($this->file->metadata['bed_temp_initial_layer'] ?? 0),
                    'other'   => (int) ($this->file->metadata['bed_temp'] ?? 0),
                ];
                if ($to && (int) $from['initial'] > 0) {
                    $bed = ['fromPlate' => $originalBedType, 'toPlate' => $this->selectedBedPlate, 'from' => $from, 'to' => $to];
                }
            }

            // Změněná teplota trysky (1. vrstva a další vrstvy zvlášť)
            $nozzleMap = $this->buildNozzleMap();
            if ($nozzleMap === null) {
                return; // chyba už je v toastu
            }

            // Vynechané objekty se z gcode odstraní ještě před odesláním
            // (spolehlivější než příkaz tiskárně po startu tisku)
            if ($bed || $nozzleMap || $this->skipObjects) {
                $patcher     = new \App\Services\GcodePatcherService();
                $patchedPath = $patcher->patch(
                    $localPath,
                    $this->selectedPlate,
                    $bed,
                    $nozzleMap,
                    $this->file->metadata['printer_model'] ?? null,
                    $this->skipObjects,
                );
                $uploadPath = $patchedPath;
                \Log::info('Gcode upraven před tiskem: ' . implode('; ', $patcher->notes));
            }

            $result = $driver->uploadFile($uploadPath, $this->file->original_name);

            // Dočasný patchnutý soubor už appka nepotřebuje - smazat ho
            // bez ohledu na to, jestli upload prošel.
            if ($patchedPath && file_exists($patchedPath)) {
                @unlink($patchedPath);
            }

            if (!$result) {
                $this->dispatch('toast', type: 'error', message: 'Chyba při odesílání souboru');
                return;
            }

            // Mapování filamentů na sloty zásobníku (AMS...)
            $materials = array_map(fn ($m) => [
                'unit'           => (int) $m['ams'],
                'slot'           => (int) $m['slot'],
                'extruder_index' => (int) ($m['extruder_index'] ?? 0),
            ], array_values($this->amsMapping));

            // Vyrovnání podložky podle zvoleného režimu
            $this->useBedLeveling = match ($this->bedLevelingMode) {
                'always' => true,
                'never'  => false,
                default  => $this->bedLevelingAdvice['level'] ?? true,
            };

            $taskId = $driver->startPrint(new \App\Printers\PrintRequest(
                remoteName: $this->file->original_name,
                plateIndex: $this->selectedPlate,
                materials:  $materials,
                options:    [
                    \App\Printers\PrintRequest::OPT_USE_MATERIAL_SYSTEM   => $this->useAms,
                    \App\Printers\PrintRequest::OPT_BED_TYPE              => $this->bedType,
                    \App\Printers\PrintRequest::OPT_BED_LEVELING          => $this->useBedLeveling,
                    \App\Printers\PrintRequest::OPT_TIMELAPSE             => $this->useTimelapse,
                    \App\Printers\PrintRequest::OPT_FIRST_LAYER_INSPECT   => $this->useLayerInspect,
                    \App\Printers\PrintRequest::OPT_FLOW_CALIBRATION      => $this->useFlowCali,
                    \App\Printers\PrintRequest::OPT_VIBRATION_CALIBRATION => $this->useVibrationCali,
                    \App\Printers\PrintRequest::OPT_SKIP_OBJECTS          => $this->skipObjects,
                ],
            ));
            $started = $taskId !== null;

	    if ($started) {
                // Záznam do historie - MQTT listener ho podle task_id spáruje,
                // jakmile se tisk na tiskárně opravdu rozběhne.
                try {
                    PrintHistoryService::queue(
                        $printer, $this->file, $this->selectedPlate, $taskId ?: null, auth()->id(),
                        $this->selectedBedPlate ?: ($this->file->metadata['bed_type'] ?? null),
                        $this->selectedBedTemp(),
                        $this->describeOverrides($bed),
                        [
                            'bed_leveling'   => $this->useBedLeveling,
                            'timelapse'      => $this->useTimelapse,
                            'flow_cali'      => $this->useFlowCali,
                            'layer_inspect'  => $this->useLayerInspect,
                            'vibration_cali' => $this->useVibrationCali,
                            'use_ams'        => $this->useAms,
                            // Pro "Tisknout znovu" - stejné nastavení dialogu
                            'bed_plate'         => $this->selectedBedPlate,
                            'bed_leveling_mode' => $this->bedLevelingMode,
                            'nozzle'            => $this->nozzleTemps,
                            'ams'               => array_map(fn ($m) => ['ams' => (int) $m['ams'], 'slot' => (int) $m['slot']], $this->amsMapping),
                            // Objekty vynechané před tiskem - MQTT listener je pošle tiskárně
                            'skip_objects'      => $this->skipObjects,
                            // odstraněno už z gcode - tiskárně se nic neposílá
                            'skip_mode'         => 'gcode',
                        ],
                    );
                } catch (\Throwable $e) {
                    \Log::error('Historie tisku (queue): ' . $e->getMessage());
                }

                $this->dispatch('toast', type: 'success',
                    message: '🖨 Tisk spuštěn na ' . $printer->name);
                $this->redirect(route('printer.detail', $printer->id));
            } else {
                $this->dispatch('toast', type: 'error', message: 'Tisk se nepodařilo spustit');
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Chyba: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $plates       = $this->file->metadata['plates'] ?? [];
        $currentPlate = collect($plates)->firstWhere('index', $this->selectedPlate) ?? ($plates[0] ?? null);
        $printers     = Printer::where('enabled', true)->get();
        $printer      = $this->printPrinterId ? Printer::find($this->printPrinterId) : null;

        return view('livewire.file-detail-page', [
            'plates'        => $plates,
            'currentPlate'  => $currentPlate,
            'printers'      => $printers,
            'dialogPrinter' => $printer,
        ]);
    }

    /** Souhrn tisků tohoto souboru + posledních 5 záznamů */
    public function getPrintHistoryProperty(): array
    {
        $base = PrintJob::where('file_id', $this->file->id)
            ->where('status', '!=', PrintJob::STATUS_QUEUED);

        $counts = (clone $base)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return [
            'total'     => (int) $counts->sum(),
            'finished'  => (int) ($counts[PrintJob::STATUS_FINISHED] ?? 0),
            'failed'    => (int) ($counts[PrintJob::STATUS_FAILED] ?? 0),
            'cancelled' => (int) ($counts[PrintJob::STATUS_CANCELLED] ?? 0),
            'running'   => (int) ($counts[PrintJob::STATUS_RUNNING] ?? 0),
            'recent'    => (clone $base)->with('printer')
                ->orderByDesc('started_at')->orderByDesc('id')
                ->limit(5)->get(),
        ];
    }

    public function getAllFoldersProperty()
    {
        return \App\Models\Folder::where('user_id', auth()->id())->orderBy('name')->get();
    }

    /**
     * Znovu naparsuje aktuální soubor - stejná logika jako FileManager::reparseFile(),
     * jen bez potřeby ID (operuje vždy nad $this->file).
     */
    public function reparseCurrentFile(): void
    {
        $this->isReparsing = true;
        $ok = (new \App\Services\FileReparseService())->reparse($this->file);
        $this->file->refresh();
        $this->isReparsing = false;

        $this->dispatch('toast', type: $ok ? 'success' : 'error',
            message: $ok ? 'Parsování dokončeno.' : 'Soubor nenalezen na disku.');
    }

    public function startRename(): void
    {
        $this->renameValue    = $this->file->original_name;
        $this->showRenameModal = true;
    }

    public function saveRename(): void
    {
        $this->validate(['renameValue' => 'required|string|max:255']);
        $this->file->update(['original_name' => $this->renameValue]);
        $this->showRenameModal = false;
    }

    public function moveTo(?int $folderId): void
    {
        $this->file->update(['folder_id' => $folderId]);
        $this->showMoveModal = false;
        $this->dispatch('toast', type: 'success', message: 'Soubor přesunut.');
    }

    public function deleteFile(): void
    {
        if ($this->file->thumbnail_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($this->file->thumbnail_path);
        }
        if (!empty($this->file->metadata['plates'])) {
            foreach ($this->file->metadata['plates'] as $plate) {
                if (!empty($plate['thumbnail_path'])) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($plate['thumbnail_path']);
                }
            }
        }
        if ($this->file->disk_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($this->file->disk_path);
        }
        $this->file->delete();
        $this->dispatch('toast', type: 'success', message: 'Soubor smazán.');
        $this->redirect(route('files'));
    }

    public function uploadToPrinter(int $printerId): void
    {
        $printer   = \App\Models\Printer::findOrFail($printerId);
        $localPath = storage_path('app/private/' . $this->file->disk_path);

        if (!file_exists($localPath)) {
            $this->dispatch('toast', type: 'error', message: 'Soubor nenalezen na disku');
            return;
        }

        try {
            $result = $printer->driver()->uploadFile($localPath, $this->file->original_name);
            if ($result) {
                $this->dispatch('toast', type: 'success',
                    message: '📤 Soubor odeslán do ' . $printer->name);
            } else {
                $this->dispatch('toast', type: 'error', message: 'Chyba při odesílání');
            }
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Chyba: ' . $e->getMessage());
        }
    }

}
