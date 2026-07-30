<div class="space-y-4">

    {{-- Detail vybrané podložky --}}
    @if($currentPlate)
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="grid grid-cols-1 lg:grid-cols-2">
                {{-- Levý sloupec: náhled + rychlé akce --}}
                <div>
                {{-- Náhled --}}
                <div class="bg-gray-50 overflow-hidden" style="height: 360px;">
                    @if(!empty($currentPlate['thumbnail_path']))
                        <img src="{{ route('file.plate.thumbnail', ['id' => $file->id, 'plateIndex' => $currentPlate['index']]) }}"
                            class="w-full h-full object-contain p-3"
                            alt="Podložka {{ $currentPlate['index'] }}">
                    @else
			<div class="w-full h-full flex items-center justify-center bg-gray-50">
                            @if(str_ends_with(strtolower($file->original_name), '.gcode'))
                                <img src="/images/gcode-placeholder.png" class="w-full h-full object-contain p-6 opacity-90">
                            @else
                                <span class="text-6xl">🗂️</span>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Rychlé akce se souborem --}}
                <div class="flex items-center justify-center gap-2 px-4 py-3 border-t border-gray-100 dark:border-bambu-dark-4">
                    <a href="{{ route('file.download', $file->id) }}"
                        class="flex items-center space-x-1.5 px-3 py-1.5 text-sm bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 dark:hover:bg-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg">
                        <span>⬇️</span><span>Stáhnout</span>
                    </a>
                    <button wire:click="reparseCurrentFile" wire:loading.attr="disabled" wire:target="reparseCurrentFile"
                        class="flex items-center space-x-1.5 px-3 py-1.5 text-sm bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 dark:hover:bg-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg disabled:opacity-50">
                        <span wire:loading.remove wire:target="reparseCurrentFile">🔄</span>
                        <span wire:loading wire:target="reparseCurrentFile" class="animate-spin">⏳</span>
                        <span>Přeparsovat</span>
                    </button>
                    <button wire:click="startRename"
                        class="flex items-center space-x-1.5 px-3 py-1.5 text-sm bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 dark:hover:bg-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg">
                        <span>✏️</span><span>Přejmenovat</span>
                    </button>
                    <button wire:click="$set('showMoveModal', true)"
                        class="flex items-center space-x-1.5 px-3 py-1.5 text-sm bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 dark:hover:bg-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg">
                        <span>📂</span><span>Přesunout</span>
                    </button>
                    <button wire:click="$set('showDeleteModal', true)"
                        class="flex items-center space-x-1.5 px-3 py-1.5 text-sm bg-red-100 dark:bg-red-900/30 hover:bg-red-200 text-red-700 dark:text-red-400 rounded-lg">
                        <span>🗑️</span><span>Smazat</span>
                    </button>
                </div>
                </div>

                {{-- Info --}}
                <div class="p-6 flex flex-col justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">{{ $file->original_name }}</h2>
                        <p class="text-sm text-gray-500 mb-4">
                            Podložka #{{ $currentPlate['index'] }}
                            @if(!empty($currentPlate['objects']))
                                · {{ implode(', ', $currentPlate['objects']) }}
                            @endif
                        </p>

			<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                            @if(!empty($currentPlate['print_time_seconds']))
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-400">Čas tisku</p>
                                    <p class="text-sm font-semibold text-gray-700 mt-0.5">⏱ {{ gmdate('H:i:s', $currentPlate['print_time_seconds']) }}</p>
                                </div>
                            @endif
                            @if(!empty($currentPlate['filament_used_g']))
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-400">Filament</p>
                                    <p class="text-sm font-semibold text-gray-700 mt-0.5">🧵 {{ $currentPlate['filament_used_g'] }}g · {{ $currentPlate['filament_used_m'] }}m</p>
                                </div>
                            @endif
                            @if(!empty($currentPlate['nozzle_diameter']))
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-400">Tryska</p>
                                    <p class="text-sm font-semibold text-gray-700 mt-0.5">⌀ {{ $currentPlate['nozzle_diameter'] }}mm</p>
                                </div>
                            @endif
			    @if(!empty($currentPlate['layer_height']))
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-400">Výška vrstvy</p>
				    <p class="text-sm font-semibold text-gray-700 mt-0.5">📏 {{ $file->metadata['layer_height'] ?? $currentPlate['layer_height'] }}mm
                                        <span class="text-xs text-gray-400 font-normal">/ první vrstva {{ $currentPlate['layer_height'] }}mm</span>
                                    </p>
                                </div>
                            @endif
                            @if(!empty($currentPlate['total_layer_num']))
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-400">Počet vrstev</p>
                                    <p class="text-sm font-semibold text-gray-700 mt-0.5">🔢 {{ $currentPlate['total_layer_num'] }}</p>
                                </div>
                            @endif
                            @if(!empty($file->metadata['nozzle_temp']))
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-400">Teplota trysky</p>
                                    <p class="text-sm font-semibold text-gray-700 mt-0.5">🌡️ {{ $file->metadata['nozzle_temp'] }}°C</p>
                                </div>
                            @endif
                            @if(!empty($file->metadata['bed_temp']))
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-400">Teplota podložky</p>
                                    <p class="text-sm font-semibold text-gray-700 mt-0.5">🟠 {{ $file->metadata['bed_temp'] }}°C
                                        <span class="text-xs text-gray-400 font-normal">/ první vrstva {{ $file->metadata['bed_temp_initial_layer'] ?? $file->metadata['bed_temp'] }}°C</span>
                                    </p>
                                </div>
                            @endif
                            @if(!empty($file->metadata['infill_density']))
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-400">Výplň</p>
                                    <p class="text-sm font-semibold text-gray-700 mt-0.5">◼ {{ $file->metadata['infill_density'] }}
                                        @if(!empty($file->metadata['infill_pattern'])) · {{ $file->metadata['infill_pattern'] }}@endif
                                    </p>
                                </div>
                            @endif
                            @if(!empty($file->metadata['print_profile']))
                                <div class="bg-gray-50 rounded-lg p-3 col-span-2">
                                    <p class="text-xs text-gray-400">Profil tisku</p>
                                    <p class="text-sm font-semibold text-gray-700 mt-0.5 truncate">{{ $file->metadata['print_profile'] }}</p>
                                </div>
                            @endif

			    @if(!empty($file->metadata['printer_model']))
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-400">Tiskárna</p>
                                    <p class="text-sm font-semibold text-gray-700 mt-0.5 truncate">{{ $file->metadata['printer_model'] }}</p>
                                </div>
                            @endif
			    @if(!empty($file->metadata['spiral_mode']) && $file->metadata['spiral_mode'])
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-400">Režim tisku</p>
                                    <p class="text-sm font-semibold text-gray-700 mt-0.5">🏺 Vázový režim</p>
                                </div>
                            @endif
                            @if(!empty($currentPlate['support_used']) && $currentPlate['support_used'])
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-400">Podpory</p>
                                    <p class="text-sm font-semibold text-gray-700 mt-0.5">🏗️ {{ $file->metadata['support_type'] ?? 'Ano' }}</p>
                                </div>
                            @endif

                        </div>

                        @if(!empty($currentPlate['filaments']))
                            <div class="mb-4">
                                <p class="text-xs text-gray-400 mb-2">Filamenty</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($currentPlate['filaments'] as $filament)
                                        <div class="flex items-center space-x-1.5 bg-gray-50 rounded-lg px-2 py-1">
                                            <div class="w-4 h-4 rounded-full border border-gray-200 shrink-0"
                                                style="background-color: {{ $filament['color'] }}"></div>
					    <span class="text-xs text-gray-600">{{ $filament['type'] }} · {{ $filament['used_g'] }}g
		                                @if(!empty($filament['used_m'])) · {{ $filament['used_m'] }}m @endif
                       			    </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

		    {{-- Tlačítka --}}
                    <div class="space-y-2">
                        @if(!empty($currentPlate['has_gcode']) && $currentPlate['has_gcode'])
                            @foreach($printers as $printer)
                                <div class="flex space-x-2">
                                    <a href="{{ route('file.download', $file->id) }}"
                                        title="Stáhnout soubor do počítače"
                                        class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl transition-colors flex items-center justify-center">
                                        ⬇️ Stáhnout
                                    </a>
                                    <button wire:click="uploadToPrinter({{ $printer->id }})"
                                        title="Odeslat soubor do tiskárny bez spuštění tisku"
                                        class="flex-1 py-2.5 bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium rounded-xl transition-colors">
                                        📤Odeslat
                                    </button>
                                    <button wire:click="openPrintDialog({{ $printer->id }})"
                                        title="Odeslat soubor a spustit tisk na {{ $printer->name }}"
                                        class="flex-1 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium rounded-xl transition-colors">
                                        🖨 Tisknout
                                    </button>
                                </div>
                            @endforeach
                        @else
                            <div class="w-full py-2.5 bg-gray-100 text-gray-400 font-medium rounded-xl text-center text-sm">
                                Bez G-code
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Výběr podložky --}}
    @if(count($plates) > 1)
        <div>
            <p class="text-xs text-gray-400 mb-2">Vyberte podložku</p>
            <div class="flex flex-wrap gap-2">
                @foreach($plates as $plate)
                    <button wire:click="selectPlate({{ $plate['index'] }})"
                        class="relative rounded-xl overflow-hidden border-2 transition-all
                            {{ $selectedPlate === $plate['index'] ? 'border-blue-500 shadow-md' : 'border-gray-200 hover:border-gray-300' }}"
                        style="width: 110px; height: 110px;">
                        @if(!empty($plate['thumbnail_path']))
                            <img src="{{ route('file.plate.thumbnail', ['id' => $file->id, 'plateIndex' => $plate['index']]) }}"
                                class="w-full h-full object-contain bg-gray-50"
                                alt="Podložka {{ $plate['index'] }}">
                        @else
                            <div class="w-full h-full bg-gray-50 flex items-center justify-center">
                                <span class="text-2xl">🗂️</span>
                            </div>
                        @endif
                        <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-40 text-white text-xs text-center py-0.5">
                            #{{ $plate['index'] }}
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Dialog tisku --}}
    @if($showPrintDialog && $dialogPrinter)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
	    <div class="bg-white rounded-xl shadow-xl mx-4 overflow-y-auto" style="max-height: 90vh; width: 580px; max-width: calc(100vw - 32px);">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900">🖨 Nastavení tisku</h3>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $dialogPrinter->name }} · Podložka #{{ $selectedPlate }}</p>
                    </div>
                    <button wire:click="closePrintDialog" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
                </div>

                <div class="p-6 space-y-5">

                    {{-- AMS mapování --}}
                    @if(!empty($amsMapping))
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Mapování filamentů → AMS</p>
                            <div class="space-y-2">
                                @foreach($amsMapping as $i => $mapping)
                                    @php
                                        $plates       = $file->metadata['plates'] ?? [];
                                        $plate        = collect($plates)->firstWhere('index', $selectedPlate);
                                        $filament     = $plate['filaments'][$i] ?? null;
                                        $amsUnits     = $dialogPrinter->status['ams']['ams'] ?? [];
                                        $selectedTray = null;
                                        $selectedAu   = null;
                                        foreach ($amsUnits as $au) {
                                            foreach ($au['tray'] as $t) {
                                                if ($au['id'] == $mapping['ams'] && $t['id'] == $mapping['slot']) {
                                                    $selectedTray = $t;
                                                    $selectedAu   = $au;
                                                }
                                            }
                                        }
                                    @endphp
                                    @if($filament)
                                        <div class="space-y-1">
                                            {{-- Řádek filametu --}}
                                            <div class="flex items-center space-x-2">
                                                <div class="flex items-center space-x-1.5 w-24 shrink-0">
                                                    <div class="w-5 h-5 rounded-full border border-gray-200 shrink-0"
                                                        style="background-color: {{ $filament['color'] }}"></div>
                                                    <span class="text-xs text-gray-600 truncate">{{ $filament['type'] }}</span>
                                                </div>
                                                <span class="text-gray-300 text-xs">→</span>
                                                @if($selectedTray)
                                                    <div class="flex items-center space-x-1.5 flex-1">
                                                        <div class="w-5 h-5 rounded-full border border-gray-200 shrink-0"
                                                            style="background-color: #{{ substr($selectedTray['tray_color'], 0, 6) }}"></div>
                                                        <span class="text-xs text-gray-600">
                                                            AMS{{ (int)$selectedAu['id']+1 }} / Slot {{ (int)$selectedTray['id']+1 }}
                                                            – {{ $selectedTray['tray_type'] ?: 'PLA' }}
                                                        </span>
                                                    </div>
                                                @endif
                                                <div class="flex space-x-1 ml-auto">
                                                    @foreach($amsUnits as $amsUnit)
                                                        <button wire:click="selectAmsFor({{ $i }}, {{ $amsUnit['id'] }})"
                                                            class="px-2 py-1 text-xs rounded-lg font-medium transition-colors
                                                                {{ $selectingAmsForFilament === $i && $selectingAmsUnit === (int)$amsUnit['id'] ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                                                            AMS{{ (int)$amsUnit['id']+1 }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>

                                            {{-- Popup výběr slotu --}}
                                            @if($selectingAmsForFilament === $i && $selectingAmsUnit !== null)
                                                <div class="bg-gray-50 rounded-xl p-3 border border-gray-200 mt-1">
                                                    @foreach($amsUnits as $amsUnit)
                                                        @if((int)$amsUnit['id'] === $selectingAmsUnit)
							    <div style="display: inline-flex; flex-wrap: wrap; gap: 8px; max-width: 100%;">
                                                                @foreach($amsUnit['tray'] as $tray)
                                                                    @php
                                                                        $trayColor  = $tray['tray_color'] ?? '00000000';
                                                                        $isEmpty    = $trayColor === '00000000';
                                                                        $colorHex   = '#' . substr($trayColor, 0, 6);
                                                                        $isSelected = $mapping['ams'] == $amsUnit['id'] && $mapping['slot'] == $tray['id'];
                                                                    @endphp
								    @if(!$isEmpty)
                                                                        <button wire:click="setAmsSlot({{ $i }}, {{ $amsUnit['id'] }}, {{ $tray['id'] }})"
                                                                            style="width: 120px; display: flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 8px; border: 2px solid {{ $isSelected ? '#1DB954' : '#4B5563' }}; background: {{ $isSelected ? '#162420' : '#374151' }}; font-size: 12px; cursor: pointer; color: #E6EDF3;">
                                                                            <div style="width: 16px; height: 16px; border-radius: 50%; border: 1px solid #6B7280; background-color: {{ $colorHex }}; flex-shrink: 0;"></div>
                                                                            <span>Slot {{ (int)$tray['id']+1 }}</span>
                                                                            <span style="color: #9ca3af;">{{ $tray['tray_type'] ?: '?' }}</span>
                                                                            @if($isSelected)<span style="color: #1DB954;">✓</span>@endif
                                                                        </button>
                                                                    @else
                                                                        <div style="width: 120px; display: flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 8px; border: 2px solid #374151; font-size: 12px; color: #6B7280; background: #2D3748;">
                                                                            <div style="width: 16px; height: 16px; border-radius: 50%; background: #4B5563; border: 1px solid #6B7280; flex-shrink: 0;"></div>
                                                                            <span>Slot {{ (int)$tray['id']+1 }}</span>
                                                                        </div>
                                                                    @endif
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif

                                            {{-- Varování o materiálu --}}
                                            @if(!empty($mapping['material_warning']))
                                                <div class="mt-1 text-xs text-orange-600 flex items-center space-x-1">
                                                    <span>⚠️</span>
                                                    <span>V AMS nebyl nalezen slot s materiálem {{ $filament['type'] ?? '' }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Nastavení tisku --}}
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Volby tisku</p>
                            <button wire:click="saveAsDefaults"
                                class="text-xs text-blue-600 hover:text-blue-800 px-2 py-1 bg-blue-50 rounded-lg">
                                💾 Uložit jako výchozí
                            </button>
                        </div>

                        @if(!empty($file->metadata['bed_temp_options']))
                            <div class="bg-gray-50 rounded-lg px-3 py-2 mb-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Typ podložky</span>
                                    <select wire:model="selectedBedPlate" class="border border-gray-300 rounded-lg px-2 py-1 text-sm bg-white">
                                        @foreach($file->metadata['bed_temp_options'] as $plateLabel => $temps)
                                            <option value="{{ $plateLabel }}">{{ $plateLabel }} ({{ $temps['initial'] }}°C)</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if($selectedBedPlate !== ($file->metadata['bed_type_canonical'] ?? $file->metadata['bed_type'] ?? ''))
                                    <p class="text-xs text-amber-600 mt-1">
                                        ⚠️ Jiná podložka, než pro kterou byl soubor naslicovaný ({{ $file->metadata['bed_type'] ?? '?' }}) - appka před tiskem upraví teplotu v souboru.
                                    </p>
                                @endif
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                                <span class="text-sm text-gray-600">Vyrovnání podložky</span>
                                <input wire:model="useBedLeveling" type="checkbox" class="rounded text-blue-600">
                            </label>
                            <label class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                                <span class="text-sm text-gray-600">Použít AMS</span>
                                <input wire:model="useAms" type="checkbox" class="rounded text-blue-600">
                            </label>
                            <label class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                                <span class="text-sm text-gray-600">Timelapse</span>
                                <input wire:model="useTimelapse" type="checkbox" class="rounded text-blue-600">
                            </label>
                            <label class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                                <span class="text-sm text-gray-600">Kontrola 1. vrstvy</span>
                                <input wire:model="useLayerInspect" type="checkbox" class="rounded text-blue-600">
                            </label>
                            <label class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                                <span class="text-sm text-gray-600">Kalibrace průtoku</span>
                                <input wire:model="useFlowCali" type="checkbox" class="rounded text-blue-600">
                            </label>
                            <label class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                                <span class="text-sm text-gray-600">Vibrační kalibrace</span>
                                <input wire:model="useVibrationCali" type="checkbox" class="rounded text-blue-600">
                            </label>
                        </div>
                    </div>

                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex space-x-3">
                    <button wire:click="closePrintDialog"
                        class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 text-sm font-medium">
                        Zrušit
                    </button>
                    <button wire:click="confirmPrint"
                        wire:loading.attr="disabled"
                        wire:target="confirmPrint"
                        class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-green-300 text-white rounded-xl text-sm font-medium">
                        <span wire:loading.remove wire:target="confirmPrint">🖨 Odeslat a tisknout</span>
                        <span wire:loading wire:target="confirmPrint">⏳ Odesílám...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Přejmenovat --}}
    @if($showRenameModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-bambu-dark-2 rounded-xl shadow-xl p-6 w-full max-w-md mx-4 border border-gray-100 dark:border-bambu-dark-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-bambu-text mb-4">✏️ Přejmenovat</h3>
                <input wire:model="renameValue" type="text"
                    class="w-full border border-gray-200 dark:border-bambu-dark-4 rounded-lg px-4 py-2 text-sm mb-4 dark:bg-bambu-dark-3 dark:text-bambu-text">
                @error('renameValue') <p class="text-red-500 text-xs mb-4">{{ $message }}</p> @enderror
                <div class="flex justify-end space-x-3">
                    <button wire:click="$set('showRenameModal', false)"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-bambu-text-dim">Zrušit</button>
                    <button wire:click="saveRename"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium">Uložit</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Přesunout --}}
    @if($showMoveModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-bambu-dark-2 rounded-xl shadow-xl p-6 w-full max-w-md mx-4 border border-gray-100 dark:border-bambu-dark-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-bambu-text mb-1">📂 Přesunout</h3>
                <p class="text-sm text-gray-500 dark:text-bambu-text-dim mb-4 truncate">{{ $file->original_name }}</p>
                <div class="space-y-1 max-h-64 overflow-y-auto mb-4">
                    <button wire:click="moveTo(null)"
                        class="w-full text-left px-3 py-2 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-bambu-dark-3 text-gray-700 dark:text-bambu-text flex items-center space-x-2">
                        <span>🏠</span><span>Kořenová složka</span>
                    </button>
                    @foreach($this->allFolders as $folder)
                        <button wire:click="moveTo({{ $folder->id }})"
                            class="w-full text-left px-3 py-2 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-bambu-dark-3 text-gray-700 dark:text-bambu-text flex items-center space-x-2">
                            <span>📁</span><span>{{ $folder->name }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="flex justify-end">
                    <button wire:click="$set('showMoveModal', false)"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-bambu-text-dim">Zrušit</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Potvrzení smazání --}}
    @if($showDeleteModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-bambu-dark-2 rounded-xl shadow-xl p-6 w-full max-w-md mx-4 border border-gray-100 dark:border-bambu-dark-4">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center shrink-0">
                        <span class="text-red-600 text-xl">🗑️</span>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-bambu-text">Smazat soubor?</h3>
                        <p class="text-sm text-gray-500 dark:text-bambu-text-dim mt-0.5">Tato akce je nevratná.</p>
                    </div>
                </div>
                <p class="text-sm text-gray-700 dark:text-bambu-text bg-gray-50 dark:bg-bambu-dark-3 rounded-lg px-3 py-2 mb-5 truncate">
                    📄 {{ $file->original_name }}
                </p>
                <div class="flex space-x-3">
                    <button wire:click="$set('showDeleteModal', false)"
                        class="flex-1 px-4 py-2 border border-gray-300 dark:border-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg text-sm">Zrušit</button>
                    <button wire:click="deleteFile"
                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium">Smazat</button>
                </div>
            </div>
        </div>
    @endif

</div>
