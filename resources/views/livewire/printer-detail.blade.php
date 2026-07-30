<div class="space-y-6" wire:poll.3000ms="refreshPrinter">

    {{-- Offline varování --}}
    @if(!$printer->is_online)
        <div class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 flex items-center space-x-3">
            <span class="text-2xl">⚠️</span>
            <div>
                <p class="font-semibold text-yellow-800">Tiskárna je offline</p>
                <p class="text-sm text-yellow-600">Zobrazená data jsou z poslední známé komunikace:
                    {{ $printer->last_seen_at?->diffForHumans() ?? 'nikdy' }}
                </p>
            </div>
        </div>
    @endif

    {{-- HMS upozornění --}}
    @if(!empty($printer->status['hms']))
        <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-red-100 bg-red-50">
                <h3 class="font-semibold text-red-800">⚠️ Upozornění tiskárny</h3>
            </div>
            <div class="p-4 space-y-2">
                @foreach($printer->status['hms'] as $hmsRaw)
                    @php $hms = \App\Services\HmsService::decode($hmsRaw); @endphp
                    <div class="flex items-start space-x-3 rounded-lg px-4 py-3
                        {{ $hms['color'] === 'red'    ? 'bg-red-50 border border-red-200'       : '' }}
                        {{ $hms['color'] === 'yellow' ? 'bg-yellow-50 border border-yellow-200' : '' }}
                        {{ $hms['color'] === 'blue'   ? 'bg-blue-50 border border-blue-200'     : '' }}">
                        <span class="text-xl shrink-0">
                            {{ $hms['color'] === 'red' ? '🔴' : ($hms['color'] === 'yellow' ? '🟡' : 'ℹ️') }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium
                                {{ $hms['color'] === 'red'    ? 'text-red-800'    : '' }}
                                {{ $hms['color'] === 'yellow' ? 'text-yellow-800' : '' }}
                                {{ $hms['color'] === 'blue'   ? 'text-blue-800'   : '' }}">
                                {{ $hms['message'] }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $hms['severity'] }} · attr: {{ $hms['attr_hex'] }} · kód: {{ $hms['code_hex'] }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Kamera + Stav --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Kamera --}}
        <div wire:ignore class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-bambu-dark-4 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 dark:text-bambu-text">📷 Kamera</h3>
                <div class="flex items-center space-x-2">
                    @if($printer->is_online)
                        <span id="cam-status" class="text-xs text-green-500">● Snapshot</span>
                        <button id="btn-stream" class="text-xs text-gray-400 hover:text-gray-600 px-2 py-1 bg-gray-100 dark:bg-bambu-dark-3 rounded-lg">▶ Live</button>
                        <button id="btn-fullscreen" class="text-xs text-gray-400 hover:text-gray-600 px-2 py-1 bg-gray-100 dark:bg-bambu-dark-3 rounded-lg hidden sm:block">⛶</button>
                    @else
                        <span class="text-xs text-gray-400">Offline</span>
                    @endif
                </div>
            </div>
            <div class="bg-black aspect-video flex items-center justify-center relative">
                @if($printer->is_online)
                    <img id="camera-img" src="{{ route('printer.snapshot', $printer->id) }}?t={{ time() }}" class="w-full h-full object-contain" alt="Kamera">
                    <iframe id="camera-video" class="hidden w-full h-full" frameborder="0" src=""></iframe>
                @endif
                <div id="cam-error" class="{{ $printer->is_online ? 'hidden' : 'flex' }} absolute inset-0 items-center justify-center text-gray-500 flex-col">
                    <span class="text-4xl mb-2">📷</span>
                    <span class="text-sm">Kamera nedostupná</span>
                </div>
            </div>
        </div>

        {{-- Stav tisku --}}
        <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-bambu-dark-4 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 dark:text-bambu-text">🖨️ Stav tisku</h3>
                @if($printer->is_online && in_array($printer->status_text, ['RUNNING', 'PAUSE']))
                    <div class="flex space-x-2">
                        @if($printer->status_text === 'RUNNING')
                            <button wire:click="confirmPause" class="px-3 py-1 bg-yellow-100 hover:bg-yellow-200 text-yellow-700 text-xs rounded-lg font-medium">⏸ Pauza</button>
                        @else
                            <button wire:click="confirmResume" class="px-3 py-1 bg-green-100 hover:bg-green-200 text-green-700 text-xs rounded-lg font-medium">▶ Pokračovat</button>
                        @endif
                        <button wire:click="confirmStop" class="px-3 py-1 bg-red-100 hover:bg-red-200 text-red-700 text-xs rounded-lg font-medium">⏹ Zastavit</button>
                    </div>
                @endif
            </div>
            <div class="p-5 space-y-3">
                @if($printer->status_text === 'RUNNING' && !empty($printer->status['subtask_name']))
                    @php
                        $subtask     = $printer->status['subtask_name'] ?? '';
                        $printFile   = $subtask ? \App\Models\File::where('user_id', auth()->id())
                            ->where('original_name', 'like', '%' . pathinfo($subtask, PATHINFO_FILENAME) . '%')
                            ->first() : null;
                        $plateIdx    = $printFile?->metadata['plates'][0]['index'] ?? 1;
                        $remaining   = $printer->status['mc_remaining_time'] ?? 0;
                        $endTime     = $remaining > 0 ? now()->timezone('Europe/Prague')->addMinutes($remaining) : null;
                        $layerHeight = $printFile?->metadata['plates'][0]['layer_height'] ?? null;
                    @endphp
                    <div class="flex items-center space-x-4 pb-3 border-b border-gray-100 dark:border-bambu-dark-4">
                        @if($printFile && !empty($printFile->metadata['plates'][0]['thumbnail_path']))
                            <img src="{{ route('file.plate.thumbnail', ['id' => $printFile->id, 'plateIndex' => $plateIdx]) }}"
                                style="width:80px;height:80px;"
                                class="object-contain rounded-xl bg-gray-50 dark:bg-bambu-dark-3 border border-gray-200 dark:border-bambu-dark-4 shrink-0">
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-gray-400 mb-0.5">Tiskne se</p>
                            <p class="text-sm font-medium text-gray-800 dark:text-bambu-text truncate">{{ $subtask }}</p>
                            @if($printer->print_progress !== null)
                                <div class="mt-2">
                                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                                        <span>{{ $printer->print_progress }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-100 dark:bg-bambu-dark-3 rounded-full h-2">
                                        <div class="bg-bambu-green h-2 rounded-full transition-all" style="width: {{ $printer->print_progress }}%"></div>
                                    </div>
                                    <div class="flex justify-between text-xs text-gray-400 mt-1 flex-wrap gap-1">
                                        <div class="flex items-center space-x-2">
                                            @if($remaining > 0)
                                                <span>⏱ {{ gmdate('H:i', $remaining * 60) }}</span>
                                            @endif
                                            @if($endTime)
                                                <span>· Konec: {{ $endTime->format('H:i') }}</span>
                                            @endif
                                        </div>
                                        @if(isset($printer->status['total_layer_num']) && $printer->status['total_layer_num'] > 0)
                                            <span>Vrstva {{ $printer->status['layer_num'] ?? 0 }}/{{ $printer->status['total_layer_num'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-3 gap-3">
                    <div class="text-center">
                        <p class="text-xs text-gray-400">Připojení</p>
                        <span class="px-2 py-0.5 text-xs rounded-full font-medium mt-1 inline-block
                            {{ $printer->is_online ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $printer->is_online ? '● Online' : '○ Offline' }}
                        </span>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-400">Stav</p>
                        <span class="px-2 py-0.5 text-xs rounded-full font-semibold mt-1 inline-block
                            @switch($printer->status_text)
                                @case('RUNNING') bg-blue-100 text-blue-700 @break
                                @case('IDLE') bg-green-100 text-green-700 @break
                                @case('PAUSE') bg-yellow-100 text-yellow-700 @break
                                @case('FAILED') bg-red-100 text-red-700 @break
                                @default bg-gray-100 text-gray-600
                            @endswitch
                        ">{{ $printer->status_text }}</span>
                    </div>
                    @if(!empty($printer->status['wifi_signal']))
                        <div class="text-center">
                            <p class="text-xs text-gray-400">WiFi</p>
                            <p class="text-sm text-gray-700 dark:text-bambu-text mt-1">📶 {{ $printer->status['wifi_signal'] }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Ovládání --}}
    @if($printer->is_online)

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Teploty --}}
            <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-bambu-dark-4">
                    <h3 class="font-semibold text-gray-800 dark:text-bambu-text">🌡️ Teploty</h3>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-gray-600 dark:text-bambu-text-dim">🔴 Tryska</span>
                            <span class="text-sm font-semibold text-gray-800 dark:text-bambu-text">{{ $temperatures['nozzle_temper'] ?? '-' }}°C
                                @if(isset($temperatures['nozzle_target_temper']))
                                    <span class="text-green-500 text-xs">→ {{ $temperatures['nozzle_target_temper'] }}°C</span>
                                @endif
                            </span>
                        </div>
                        <div class="flex space-x-2">
                            <input wire:model="targetHotend" type="number" min="0" max="300" step="5" class="flex-1 border border-gray-300 dark:border-bambu-dark-4 rounded-lg px-3 py-1.5 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
                            <button wire:click="applyHotendTemp" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg">Nastavit</button>
                            <button wire:click="hotendOff" class="px-3 py-1.5 bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 text-gray-600 dark:text-bambu-text text-sm rounded-lg">Vypnout</button>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-gray-600 dark:text-bambu-text-dim">🟠 Podložka</span>
                            <span class="text-sm font-semibold text-gray-800 dark:text-bambu-text">{{ $temperatures['bed_temper'] ?? '-' }}°C
                                @if(isset($temperatures['bed_target_temper']))
                                    <span class="text-green-500 text-xs">→ {{ $temperatures['bed_target_temper'] }}°C</span>
                                @endif
                            </span>
                        </div>
                        <div class="flex space-x-2">
                            <input wire:model="targetBed" type="number" min="0" max="120" step="5" class="flex-1 border border-gray-300 dark:border-bambu-dark-4 rounded-lg px-3 py-1.5 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
                            <button wire:click="applyBedTemp" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg">Nastavit</button>
                            <button wire:click="bedOff" class="px-3 py-1.5 bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 text-gray-600 dark:text-bambu-text text-sm rounded-lg">Vypnout</button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between py-2 border-t border-gray-100 dark:border-bambu-dark-4">
                        <span class="text-sm text-gray-600 dark:text-bambu-text-dim">🔵 Komora</span>
                        <span class="text-sm font-semibold text-gray-800 dark:text-bambu-text">{{ $temperatures['chamber_temper'] ?? '-' }}°C</span>
                    </div>
                </div>
            </div>

            {{-- Pohyb os --}}
            <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-bambu-dark-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold text-gray-800 dark:text-bambu-text">🕹️ Pohyb os</h3>
                    </div>
                    <div class="flex items-center flex-wrap gap-1.5">
                        <span class="text-xs text-gray-400">Vzdálenost:</span>
                        @foreach([1, 10, 50] as $dist)
                            <button wire:click="$set('jogDistance', {{ $dist }})"
                                class="px-2 py-1 text-xs rounded-lg {{ $jogDistance == $dist ? 'bg-green-600 text-white' : 'bg-gray-100 dark:bg-bambu-dark-3 text-gray-600 dark:text-bambu-text-dim hover:bg-gray-200' }}">
                                {{ $dist }}mm
                            </button>
                        @endforeach
                        <div class="flex items-center space-x-1">
                            <input wire:model="jogCustom" wire:keydown.enter="setJogFromCustom"
                                type="number" min="0.1" max="200" step="0.1" placeholder="vlastní"
                                class="w-16 border border-gray-300 dark:border-bambu-dark-4 rounded-lg px-2 py-1 text-xs dark:bg-bambu-dark-3 dark:text-bambu-text">
                            <button wire:click="setJogFromCustom" class="px-2 py-1 text-xs bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 text-gray-600 dark:text-bambu-text-dim rounded-lg">OK</button>
                        </div>
                        @if(!in_array($jogDistance, [1, 10, 50]))
                            <span class="px-2 py-1 text-xs bg-green-600 text-white rounded-lg">{{ $jogDistance }}mm</span>
                        @endif
                    </div>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div class="flex flex-col items-center">
                            <p class="text-xs text-gray-400 dark:text-bambu-text-dim mb-3">X / Y</p>
                            <div class="grid grid-cols-3 gap-1" style="width:120px;">
                                <div></div>
                                <button wire:click="moveAxis('Y', {{ $jogDistance }})" class="p-2 bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 rounded-lg text-sm font-bold">▲</button>
                                <div></div>
                                <button wire:click="moveAxis('X', -{{ $jogDistance }})" class="p-2 bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 rounded-lg text-sm font-bold">◄</button>
                                <button wire:click="homeAxis('XY')" class="p-2 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg text-xs font-bold">🏠</button>
                                <button wire:click="moveAxis('X', {{ $jogDistance }})" class="p-2 bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 rounded-lg text-sm font-bold">►</button>
                                <div></div>
                                <button wire:click="moveAxis('Y', -{{ $jogDistance }})" class="p-2 bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 rounded-lg text-sm font-bold">▼</button>
                                <div></div>
                            </div>
                        </div>
                        <div class="flex flex-col items-center">
                            <p class="text-xs text-gray-400 dark:text-bambu-text-dim mb-3">Z</p>
                            <div class="flex flex-col items-center gap-1">
                                <button wire:click="moveAxis('Z', -{{ $jogDistance }})" class="p-2 bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 rounded-lg text-sm font-bold">▲</button>
                                <button wire:click="homeAxis('Z')" class="p-2 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg text-xs font-bold w-10 text-center">🏠</button>
                                <button wire:click="moveAxis('Z', {{ $jogDistance }})" class="p-2 bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 rounded-lg text-sm font-bold">▼</button>
                            </div>
                        </div>
                        <div class="flex flex-col justify-center space-y-2">
                            <button wire:click="homeAll" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg font-medium">🏠 Home All</button>
                            <button wire:click="homeAxis('X')" class="px-4 py-2 bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 text-gray-700 dark:text-bambu-text text-sm rounded-lg">🏠 Home X</button>
                            <button wire:click="homeAxis('Y')" class="px-4 py-2 bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 text-gray-700 dark:text-bambu-text text-sm rounded-lg">🏠 Home Y</button>
                            <button wire:click="homeAxis('Z')" class="px-4 py-2 bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 text-gray-700 dark:text-bambu-text text-sm rounded-lg">🏠 Home Z</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Ovládání --}}
            <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-bambu-dark-4">
                    <h3 class="font-semibold text-gray-800 dark:text-bambu-text">⚙️ Ovládání</h3>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-gray-600 dark:text-bambu-text-dim">🚀 Rychlost tisku</span>
                            <span class="text-sm font-semibold text-gray-800 dark:text-bambu-text">{{ $printer->status['spd_mag'] ?? 100 }}%</span>
                        </div>
                        <div class="flex space-x-2">
                            <input wire:model="targetSpeed" type="number" min="50" max="200" step="10" class="flex-1 border border-gray-300 dark:border-bambu-dark-4 rounded-lg px-3 py-1.5 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
                            <button wire:click="applySpeed" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg">Nastavit</button>
                        </div>
                    </div>
                    @foreach([
                        ['label' => '💨 Ventilátor (cooling)', 'key' => 'cooling_fan_speed', 'model' => 'targetFanCooling', 'action' => 'applyFanCooling'],
                        ['label' => '🌀 Aux ventilátor',       'key' => 'big_fan1_speed',    'model' => 'targetFanAux',     'action' => 'applyFanAux'],
                        ['label' => '🔄 Filtr ventilátor',     'key' => 'big_fan2_speed',    'model' => 'targetFanFilter',  'action' => 'applyFanFilter'],
                    ] as $fan)
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-600 dark:text-bambu-text-dim">{{ $fan['label'] }}</span>
                                <span class="text-sm font-semibold text-gray-800 dark:text-bambu-text">{{ isset($fans[$fan['key']]) ? round($fans[$fan['key']] / 15 * 100) : 0 }}%</span>
                            </div>
                            <div class="flex space-x-2">
                                <input wire:model="{{ $fan['model'] }}" type="number" min="0" max="100" step="10" class="flex-1 border border-gray-300 dark:border-bambu-dark-4 rounded-lg px-3 py-1.5 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
                                <button wire:click="{{ $fan['action'] }}" class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg">Nastavit</button>
                            </div>
                        </div>
                    @endforeach
                    @if(!empty($printer->status['lights']))
                        <div class="border-t border-gray-100 dark:border-bambu-dark-4 pt-3">
                            <p class="text-sm text-gray-600 dark:text-bambu-text-dim mb-2">💡 Světla</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach(array_filter($printer->status['lights'], fn($l) => $l['node'] !== 'work_light') as $light)
                                    <button wire:click="toggleLight('{{ $light['node'] }}')"
                                        class="flex items-center space-x-2 px-3 py-1.5 rounded-lg text-sm transition-colors
                                            {{ $light['mode'] === 'on' ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' : 'bg-gray-100 dark:bg-bambu-dark-3 text-gray-500 dark:text-bambu-text-dim hover:bg-gray-200' }}">
                                        <span>{{ $light['mode'] === 'on' ? '💡' : '🔦' }}</span>
                                        <span>{{ str_replace('_', ' ', $light['node']) }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Teploty offline --}}
    @if(!$printer->is_online && !empty($printer->status['temperatures']))
        <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden opacity-50">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-bambu-dark-4">
                <h3 class="font-semibold text-gray-800 dark:text-bambu-text">🌡️ Poslední známé teploty</h3>
            </div>
            <div class="p-5 grid grid-cols-3 gap-4">
                <div class="text-center"><p class="text-xs text-gray-400">Tryska</p><p class="text-xl font-bold dark:text-bambu-text">{{ $temperatures['nozzle_temper'] ?? '-' }}°C</p></div>
                <div class="text-center"><p class="text-xs text-gray-400">Podložka</p><p class="text-xl font-bold dark:text-bambu-text">{{ $temperatures['bed_temper'] ?? '-' }}°C</p></div>
                <div class="text-center"><p class="text-xs text-gray-400">Komora</p><p class="text-xl font-bold dark:text-bambu-text">{{ $temperatures['chamber_temper'] ?? '-' }}°C</p></div>
            </div>
        </div>
    @endif

    {{-- AMS --}}
    @if(!empty($printer->status['ams']['ams']))
        <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-bambu-dark-4">
                <h3 class="font-semibold text-gray-800 dark:text-bambu-text">🎨 AMS</h3>
            </div>
            <div class="p-5 space-y-6">
                @foreach($printer->status['ams']['ams'] as $ams)
                    <div>
                        <div class="flex items-center space-x-2 mb-3">
                            <span class="text-sm font-semibold text-gray-700 dark:text-bambu-text">AMS {{ (int)$ams['id'] + 1 }}</span>
                            <span class="text-xs text-gray-400">🌡️ {{ $ams['temp'] }}°C</span>
                            <span class="text-xs text-gray-400">💧 {{ $ams['humidity'] }}</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            @foreach($ams['tray'] as $tray)
                                @php
                                    $trayColor = $tray['tray_color'] ?? '00000000';
                                    $isEmpty   = $trayColor === '00000000';
                                    $colorHex  = '#' . substr($trayColor, 0, 6);
                                    $hasNfc    = !empty($tray['tray_uuid']) && $tray['tray_uuid'] !== '00000000000000000000000000000000';
                                    $isActive  = isset($printer->status['ams']['tray_now']) &&
                                        (string)$printer->status['ams']['tray_now'] === (string)($ams['id'] * 4 + $tray['id']);
                                @endphp
                                <div class="border-2 rounded-xl p-3 {{ $isActive ? 'border-green-400 bg-green-50 dark:bg-green-900/20' : 'border-gray-100 dark:border-bambu-dark-4' }}">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <div class="w-7 h-7 rounded-full border border-gray-200 shrink-0" style="background-color: {{ $isEmpty ? '#e5e7eb' : $colorHex }}"></div>
                                        <span class="text-xs font-semibold text-gray-700 dark:text-bambu-text">Slot {{ (int)$tray['id'] + 1 }}</span>
                                        @if($isActive)<span class="text-xs text-green-600 dark:text-bambu-green font-bold">● Aktivní</span>@endif
                                    </div>
                                    @if(!$isEmpty)
                                        <p class="text-xs font-medium text-gray-800 dark:text-bambu-text">{{ $tray['tray_type'] ?: 'PLA' }}</p>
                                        @if($hasNfc && !empty($tray['tray_sub_brands']))
                                            <p class="text-xs text-gray-500 dark:text-bambu-text-dim">{{ $tray['tray_sub_brands'] }}</p>
                                        @elseif(!$hasNfc)
                                            <p class="text-xs text-gray-400 italic">Bez NFC tagu</p>
                                        @endif
                                        @if($tray['nozzle_temp_min'] > 0)
                                            <p class="text-xs text-gray-400 mt-1">🌡️ {{ $tray['nozzle_temp_min'] }}–{{ $tray['nozzle_temp_max'] }}°C</p>
                                        @endif
                                    @else
                                        <p class="text-xs text-gray-400 italic">Prázdný slot</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Fullscreen --}}
    <div id="camera-fullscreen" class="hidden fixed inset-0 bg-black z-50 flex flex-col">
        <div class="flex items-center justify-between px-4 py-2">
            <span class="text-white text-sm">📷 {{ $printer->name }}</span>
            <button id="btn-fullscreen-close" class="text-white text-2xl hover:text-gray-300">✕</button>
        </div>
        <div class="flex-1 flex items-center justify-center">
            <iframe id="camera-video-fs" class="hidden w-full h-full" frameborder="0" src=""></iframe>
            <img id="camera-img-fs" class="hidden max-w-full max-h-full object-contain" alt="Kamera">
        </div>
    </div>

    {{-- Dialog: Pauza --}}
    @if($showPauseConfirm)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-bambu-dark-2 rounded-xl shadow-xl p-6 mx-4 w-full max-w-sm border border-gray-100 dark:border-bambu-dark-4">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center shrink-0"><span class="text-xl">⏸</span></div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-bambu-text">Pozastavit tisk?</h3>
                        <p class="text-sm text-gray-500 dark:text-bambu-text-dim mt-0.5">Tisk bude pozastaven a lze ho obnovit.</p>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <button wire:click="$set('showPauseConfirm', false)" class="flex-1 px-4 py-2 border border-gray-300 dark:border-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg text-sm">Zrušit</button>
                    <button wire:click="pausePrint" class="flex-1 px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg text-sm font-medium">⏸ Pozastavit</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Dialog: Pokračovat --}}
    @if($showResumeConfirm)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-bambu-dark-2 rounded-xl shadow-xl p-6 mx-4 w-full max-w-sm border border-gray-100 dark:border-bambu-dark-4">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center shrink-0"><span class="text-xl">▶</span></div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-bambu-text">Obnovit tisk?</h3>
                        <p class="text-sm text-gray-500 dark:text-bambu-text-dim mt-0.5">Tisk bude obnoven od místa zastavení.</p>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <button wire:click="$set('showResumeConfirm', false)" class="flex-1 px-4 py-2 border border-gray-300 dark:border-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg text-sm">Zrušit</button>
                    <button wire:click="resumePrint" class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium">▶ Obnovit</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Dialog: Stop --}}
    @if($showStopConfirm)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-bambu-dark-2 rounded-xl shadow-xl p-6 mx-4 w-full max-w-sm border border-gray-100 dark:border-bambu-dark-4">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center shrink-0"><span class="text-xl">⏹</span></div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-bambu-text">Zastavit tisk?</h3>
                        <p class="text-sm text-gray-500 dark:text-bambu-text-dim mt-0.5">Tato akce je nevratná – tisk bude zrušen.</p>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <button wire:click="$set('showStopConfirm', false)" class="flex-1 px-4 py-2 border border-gray-300 dark:border-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg text-sm">Zrušit</button>
                    <button wire:click="stopPrint" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium">⏹ Zastavit tisk</button>
                </div>
            </div>
        </div>
    @endif

</div>

<script>
(function initCamera() {
    var snapshotUrl = '{{ route("printer.snapshot", $printer->id) }}';
    var go2rtcUrl   = '/go2rtc/stream.html?src=printer_{{ $printer->id }}&controls=false';
    var isStreaming  = false;
    var snapshotInterval = null;

    function getEl(id) { return document.getElementById(id); }

    function startSnapshotRefresh() {
        clearInterval(snapshotInterval);
        snapshotInterval = setInterval(function() {
            if (isStreaming) return;
            var img = getEl('camera-img');
            if (img) img.src = snapshotUrl + '?t=' + Date.now();
        }, 5000);
    }

    function startStream() {
        isStreaming = true;
        clearInterval(snapshotInterval);
        var img = getEl('camera-img'), video = getEl('camera-video');
        if (img) img.classList.add('hidden');
        if (video) { video.classList.remove('hidden'); video.setAttribute('src', go2rtcUrl); }
        var s = getEl('cam-status'), b = getEl('btn-stream');
        if (s) { s.textContent = '● Live'; s.className = 'text-xs text-red-500'; }
        if (b) b.textContent = '⏹ Stop';
    }

    function stopStream() {
        isStreaming = false;
        var img = getEl('camera-img'), video = getEl('camera-video');
        if (video) { video.setAttribute('src', ''); video.classList.add('hidden'); }
        if (img) { img.classList.remove('hidden'); img.src = snapshotUrl + '?t=' + Date.now(); }
        var s = getEl('cam-status'), b = getEl('btn-stream');
        if (s) { s.textContent = '● Snapshot'; s.className = 'text-xs text-green-500'; }
        if (b) b.textContent = '▶ Live';
        startSnapshotRefresh();
    }

    function openFullscreen() {
        var fs = getEl('camera-fullscreen');
        if (fs) fs.classList.remove('hidden');
        var vfs = getEl('camera-video-fs'), ifs = getEl('camera-img-fs');
        if (isStreaming) {
            if (ifs) ifs.classList.add('hidden');
            if (vfs) { vfs.classList.remove('hidden'); vfs.setAttribute('src', go2rtcUrl); }
        } else {
            if (vfs) { vfs.setAttribute('src', ''); vfs.classList.add('hidden'); }
            if (ifs) { ifs.classList.remove('hidden'); ifs.src = snapshotUrl + '?t=' + Date.now(); }
        }
    }

    function closeFullscreen() {
        var fs = getEl('camera-fullscreen');
        if (fs) fs.classList.add('hidden');
        var vfs = getEl('camera-video-fs'), ifs = getEl('camera-img-fs');
        if (vfs) { vfs.setAttribute('src', ''); vfs.classList.add('hidden'); }
        if (ifs) ifs.classList.add('hidden');
    }

    var bs = getEl('btn-stream'), bf = getEl('btn-fullscreen'), bc = getEl('btn-fullscreen-close');
    if (bs) bs.onclick = function() { isStreaming ? stopStream() : startStream(); };
    if (bf) bf.onclick = openFullscreen;
    if (bc) bc.onclick = closeFullscreen;

    startSnapshotRefresh();
})();
</script>
