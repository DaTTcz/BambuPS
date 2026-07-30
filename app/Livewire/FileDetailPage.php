<?php

namespace App\Livewire;

use App\Models\File;
use App\Models\Printer;
use App\Services\BambuFtpService;
use App\Services\PrinterCommandService;
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
    public bool $useTimelapse     = false;
    public bool $useAms           = true;
    public bool $useLayerInspect  = false;
    public bool $useFlowCali      = false;
    public bool $useVibrationCali = false;
    public string $bedType        = 'auto';
    // Vybraný typ FYZICKÉ podložky pro patch teploty v gcode - nesouvisí
    // s $bedType výše (to je MQTT pole, které má zůstat "auto").
    public string $selectedBedPlate = '';

    // Rychlé akce (stejné jako v přehledu souborů)
    public bool $isReparsing      = false;
    public bool $showRenameModal  = false;
    public string $renameValue    = '';
    public bool $showMoveModal    = false;
    public bool $showDeleteModal  = false;

    public function mount(File $file): void
    {
        $this->file = $file;
        $plates = $file->metadata['plates'] ?? [];
        if (!empty($plates)) {
            $this->selectedPlate = $plates[0]['index'] ?? 1;
        }
    }

    public function selectPlate(int $index): void
    {
        $this->selectedPlate = $index;
    }

    public function openPrintDialog(int $printerId): void
    {
        $this->printPrinterId          = $printerId;
        $this->amsMapping              = [];
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
        $this->useTimelapse     = $defaults['useTimelapse']      ?? false;
        $this->useAms           = $defaults['useAms']            ?? true;
        $this->useLayerInspect  = $defaults['useLayerInspect']   ?? false;
        $this->useFlowCali      = $defaults['useFlowCali']       ?? false;
        $this->useVibrationCali = $defaults['useVibrationCali']  ?? false;
        $this->bedType          = $defaults['bedType']           ?? 'auto';
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

    public function saveAsDefaults(): void
    {
        session([
            'print_defaults' => [
                'useBedLeveling'   => $this->useBedLeveling,
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

    public function confirmPrint(): void
    {
        $printer   = Printer::findOrFail($this->printPrinterId);
        $localPath = storage_path('app/private/' . $this->file->disk_path);

        if (!file_exists($localPath)) {
            $this->dispatch('toast', type: 'error', message: 'Soubor nenalezen na disku');
            return;
        }

        try {
            $ftp = new BambuFtpService($printer);

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

            if ($this->selectedBedPlate && $this->selectedBedPlate !== $originalBedType) {
                $options = $this->file->metadata['bed_temp_options'] ?? [];
                if (isset($options[$this->selectedBedPlate]['initial'])) {
                    $patcher     = new \App\Services\GcodePatcherService();
                    $patchedPath = $patcher->patchBedTemp(
                        $localPath,
                        $this->selectedPlate,
                        (int) $options[$this->selectedBedPlate]['initial']
                    );
                    $uploadPath = $patchedPath;
                }
            }

            // Smazat starý soubor před nahráním nového - bez tohoto kroku
            // přepisujeme stejný soubor přes FTP STOR, což nemusí vyčistit
            // interní stav/task tracking tiskárny vázaný na předchozí (např.
            // neúspěšný) tisk se stejným jménem souboru.
            $ftp->delete($this->file->original_name);

            $result = $ftp->upload($uploadPath, $this->file->original_name);

            // Dočasný patchnutý soubor už appka nepotřebuje - smazat ho
            // bez ohledu na to, jestli upload prošel.
            if ($patchedPath && file_exists($patchedPath)) {
                @unlink($patchedPath);
            }

            if (!$result) {
                $this->dispatch('toast', type: 'error', message: 'Chyba při odesílání souboru');
                return;
            }

	    // Transformace AMS mappingu do formátu který BambuLab očekává
            $bambuAmsMapping = array_map(function($m) {
                return [
                    'ams'            => (int) $m['ams'],
                    'slot'           => (int) $m['slot'],
                    'tray_id'        => (int) $m['ams'] * 4 + (int) $m['slot'],
                    'extruder_index' => (int) ($m['extruder_index'] ?? 0),
                ];
            }, array_values($this->amsMapping));

            $cmd     = new PrinterCommandService($printer);
            $started = $cmd->startPrint(
                $this->file->original_name,
                $this->selectedPlate,
                $this->useAms,
                $this->bedType,
		$bambuAmsMapping,
	        $this->useBedLeveling,
                $this->useTimelapse,
                $this->useLayerInspect,
                $this->useFlowCali,
                $this->useVibrationCali,
            );

	    if ($started) {
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
        $fullPath = storage_path('app/private/' . $this->file->disk_path);

        if (!file_exists($fullPath)) {
            $this->isReparsing = false;
            $this->dispatch('toast', type: 'error', message: 'Soubor nenalezen na disku.');
            return;
        }

        $extension = strtolower(pathinfo($this->file->disk_path, PATHINFO_EXTENSION));
        if ($extension === '3mf') {
            $metadata = (new \App\Services\ThreeMfParserService())->parse($fullPath);
        } else {
            $metadata = (new \App\Services\GcodeParserService())->parse($fullPath);
        }

        if (!empty($metadata['thumbnail_data'])) {
            if ($this->file->thumbnail_path) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($this->file->thumbnail_path);
            }
            $thumbName     = \Illuminate\Support\Str::uuid() . '.png';
            $thumbnailPath = 'thumbnails/' . $thumbName;
            \Illuminate\Support\Facades\Storage::disk('local')->put($thumbnailPath, base64_decode($metadata['thumbnail_data']));
            unset($metadata['thumbnail_data']);
            $this->file->thumbnail_path = $thumbnailPath;
        }

        if (!empty($metadata['plates'])) {
            if (!empty($this->file->metadata['plates'])) {
                foreach ($this->file->metadata['plates'] as $oldPlate) {
                    if (!empty($oldPlate['thumbnail_path'])) {
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($oldPlate['thumbnail_path']);
                    }
                }
            }
            foreach ($metadata['plates'] as $i => $plate) {
                if (!empty($plate['thumbnail_data'])) {
                    $plateThumbName = \Illuminate\Support\Str::uuid() . '.png';
                    $plateThumbPath = 'thumbnails/' . $plateThumbName;
                    \Illuminate\Support\Facades\Storage::disk('local')->put($plateThumbPath, base64_decode($plate['thumbnail_data']));
                    $metadata['plates'][$i]['thumbnail_path'] = $plateThumbPath;
                    unset($metadata['plates'][$i]['thumbnail_data']);
                }
            }
        }

        $this->file->metadata = $metadata;
        $this->file->save();
        $this->file->refresh();
        $this->isReparsing = false;
        $this->dispatch('toast', type: 'success', message: 'Parsování dokončeno.');
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
            $ftp    = new BambuFtpService($printer);
            $result = $ftp->upload($localPath, $this->file->original_name);
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
