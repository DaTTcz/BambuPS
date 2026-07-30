@props(['printer', 'showControls' => true])

<div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden hover:border-green-400 dark:hover:border-bambu-green hover:shadow-md transition-all">

    {{-- Kamera --}}
    <a href="{{ route('printer.detail', $printer->id) }}" class="block relative">
        <div class="bg-black aspect-video relative overflow-hidden">
            @if($printer->is_online)
                <img src="{{ route('printer.snapshot', $printer->id) }}?t={{ time() }}"
                    class="w-full h-full object-contain"
                    alt="{{ $printer->name }}">
            @else
                <div class="w-full h-full flex items-center justify-center text-gray-600">
                    <div class="text-center">
                        <p class="text-4xl mb-2 opacity-30">📷</p>
                        <p class="text-xs text-gray-500">Offline</p>
                    </div>
                </div>
            @endif

            {{-- Status badge --}}
            <div class="absolute top-2 left-2">
                <span class="px-2 py-0.5 text-xs rounded-full font-medium backdrop-blur-sm
                    {{ $printer->is_online ? 'bg-green-500/90 text-white' : 'bg-gray-800/80 text-gray-300' }}">
                    {{ $printer->is_online ? '● Online' : '○ Offline' }}
                </span>
            </div>

            {{-- Progress overlay při tisku --}}
            @if($printer->status_text === 'RUNNING' && $printer->print_progress !== null)
                @php
                    $remaining  = $printer->status['mc_remaining_time'] ?? 0;
                    $endTime    = $remaining > 0 ? now()->timezone('Europe/Prague')->addMinutes($remaining) : null;
                    $subtask    = $printer->status['subtask_name'] ?? '';
                    $printFile  = $subtask ? \App\Models\File::where('user_id', auth()->id())
                        ->where('original_name', 'like', '%' . pathinfo($subtask, PATHINFO_FILENAME) . '%')
                        ->first() : null;
                    $plateIndex = $printFile?->metadata['plates'][0]['index'] ?? 1;
                @endphp
                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent px-3 py-3">
                    <div class="flex items-center space-x-2 mb-1.5">
                        @if($printFile && !empty($printFile->metadata['plates'][0]['thumbnail_path']))
                            <img src="{{ route('file.plate.thumbnail', ['id' => $printFile->id, 'plateIndex' => $plateIndex]) }}"
                                class="w-8 h-8 object-contain rounded shrink-0 bg-black/40">
                        @endif
                        <div class="flex items-center justify-between text-white text-xs flex-1 min-w-0">
                            <span class="truncate opacity-90">{{ $subtask }}</span>
                            <span class="shrink-0 ml-2 font-semibold">{{ $printer->print_progress }}%</span>
                        </div>
                    </div>
                    <div class="w-full bg-white/20 rounded-full h-1">
                        <div class="bg-bambu-green h-1 rounded-full transition-all" style="width: {{ $printer->print_progress }}%"></div>
                    </div>
                    <div class="flex items-center justify-between mt-1 text-xs text-gray-300">
                        @if($remaining > 0)
                            <span>⏱ {{ gmdate('H:i', $remaining * 60) }}</span>
                        @endif
                        @if($endTime)
                            <span>Konec {{ $endTime->format('H:i') }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </a>

    {{-- Info --}}
    <div class="p-4">
        <div class="flex items-center justify-between mb-2">
            <a href="{{ route('printer.detail', $printer->id) }}"
                class="font-semibold text-gray-800 dark:text-bambu-text hover:text-green-600 dark:hover:text-bambu-green transition-colors">
                {{ $printer->name }}
            </a>
            <span class="px-2 py-0.5 text-xs rounded-full font-medium
                @switch($printer->status_text)
                    @case('RUNNING') bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 @break
                    @case('IDLE') bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-bambu-green @break
                    @case('PAUSE') bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400 @break
                    @case('FAILED') bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 @break
                    @default bg-gray-100 text-gray-500 dark:bg-bambu-dark-3 dark:text-bambu-text-dim
                @endswitch
            ">{{ $printer->status_text }}</span>
        </div>

        @if($printer->is_online)
            {{-- Teploty --}}
            <div class="flex items-center space-x-3 text-xs text-gray-500 dark:text-bambu-text-dim mb-2">
                <span>🔴 {{ $printer->status['temperatures']['nozzle_temper'] ?? '-' }}°C</span>
                <span>🟠 {{ $printer->status['temperatures']['bed_temper'] ?? '-' }}°C</span>
                @if(!empty($printer->status['temperatures']['chamber_temper']))
                    <span>🔵 {{ $printer->status['temperatures']['chamber_temper'] }}°C</span>
                @endif
            </div>

            {{-- AMS barvy --}}
            @if(!empty($printer->status['ams']['ams']))
                <div class="flex items-center space-x-1 mb-2">
                    @foreach($printer->status['ams']['ams'] as $ams)
                        @foreach($ams['tray'] as $tray)
                            @php
                                $trayColor = $tray['tray_color'] ?? '00000000';
                                $isEmpty   = $trayColor === '00000000';
                                $colorHex  = '#' . substr($trayColor, 0, 6);
                                $isActive  = isset($printer->status['ams']['tray_now']) &&
                                    (string)$printer->status['ams']['tray_now'] === (string)($ams['id'] * 4 + $tray['id']);
                            @endphp
                            <div class="w-4 h-4 rounded-full border-2 transition-all
                                {{ $isActive ? 'border-green-400 scale-110' : 'border-gray-200 dark:border-bambu-dark-4' }}"
                                style="background-color: {{ $isEmpty ? '#e5e7eb' : $colorHex }}"></div>
                        @endforeach
                    @endforeach
                </div>
            @endif

            {{-- Ovládání tisku --}}
            @if($showControls && in_array($printer->status_text, ['RUNNING', 'PAUSE']))
                <div class="flex space-x-2 pt-2 border-t border-gray-100 dark:border-bambu-dark-4">
                    @if($printer->status_text === 'RUNNING')
                        <button wire:click="pausePrint({{ $printer->id }})"
                            class="flex-1 py-1.5 bg-yellow-100 hover:bg-yellow-200 dark:bg-yellow-900/30 dark:hover:bg-yellow-900/50 text-yellow-700 dark:text-yellow-400 text-xs rounded-lg font-medium transition-colors">
                            ⏸ Pauza
                        </button>
                    @else
                        <button wire:click="resumePrint({{ $printer->id }})"
                            class="flex-1 py-1.5 bg-green-100 hover:bg-green-200 dark:bg-green-900/30 dark:hover:bg-green-900/50 text-green-700 dark:text-bambu-green text-xs rounded-lg font-medium transition-colors">
                            ▶ Pokračovat
                        </button>
                    @endif
                    <button wire:click="stopPrint({{ $printer->id }})"
                        class="flex-1 py-1.5 bg-red-100 hover:bg-red-200 dark:bg-red-900/30 dark:hover:bg-red-900/50 text-red-700 dark:text-red-400 text-xs rounded-lg font-medium transition-colors">
                        ⏹ Stop
                    </button>
                </div>
            @endif
        @endif
    </div>
</div>
