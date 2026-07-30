<div>
    @if($show && $file)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-screen overflow-y-auto mx-4">

                {{-- Header --}}
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-800">{{ $file->original_name }}</h3>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $file->size_formatted }}
                            @if(!empty($file->metadata['printer_model']))
                                · 🖨 {{ $file->metadata['printer_model'] }}
                            @endif
                        </p>
                    </div>
                    <button wire:click="close" class="text-gray-400 hover:text-gray-600 text-2xl">✕</button>
                </div>

                <div class="p-6">

                    {{-- Souhrnné info --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                        @if(!empty($file->metadata['print_time']))
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <p class="text-xs text-gray-400">Čas tisku</p>
                                <p class="text-sm font-semibold text-gray-700 mt-1">⏱ {{ $file->metadata['print_time'] }}</p>
                            </div>
                        @endif
                        @if(!empty($file->metadata['filament_used_g']))
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <p class="text-xs text-gray-400">Filament</p>
                                <p class="text-sm font-semibold text-gray-700 mt-1">🧵 {{ $file->metadata['filament_used_g'] }}g</p>
                            </div>
                        @endif
                        @if(!empty($file->metadata['plate_count']))
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <p class="text-xs text-gray-400">Podložky</p>
                                <p class="text-sm font-semibold text-gray-700 mt-1">{{ $file->metadata['plate_count'] }}x</p>
                            </div>
                        @endif
                        @if(!empty($file->metadata['filament_type']))
                            <div class="bg-gray-50 rounded-lg p-3 text-center">
                                <p class="text-xs text-gray-400">Materiál</p>
                                <p class="text-sm font-semibold text-gray-700 mt-1">{{ $file->metadata['filament_type'] }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Podložky --}}
                    @if(count($plates) > 0)
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Podložky</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($plates as $plate)
                                <div class="border border-gray-200 rounded-xl overflow-hidden">

                                    {{-- Náhled --}}
                                    <div class="bg-gray-50 h-40 overflow-hidden flex items-center justify-center">
                                        @if(!empty($plate['thumbnail_path']))
                                            <img src="{{ route('file.plate.thumbnail', ['id' => $file->id, 'plateIndex' => $plate['index']]) }}"
                                                class="max-h-40 max-w-full object-contain"
                                                alt="Podložka {{ $plate['index'] }}">
                                        @else
                                            <span class="text-4xl">🗂️</span>
                                        @endif
                                    </div>

                                    {{-- Info --}}
                                    <div class="p-3">
                                        <div class="flex items-center justify-between mb-2">
                                            <p class="text-sm font-semibold text-gray-800">Podložka {{ $plate['index'] }}</p>
                                            @if(!empty($plate['has_gcode']) && $plate['has_gcode'])
                                                <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">G-code</span>
                                            @endif
                                        </div>

                                        @if(!empty($plate['objects']))
                                            <p class="text-xs text-gray-500 mb-1">📦 {{ implode(', ', $plate['objects']) }}</p>
                                        @endif

                                        <div class="flex flex-wrap gap-2 text-xs text-gray-500">
                                            @if(!empty($plate['print_time_seconds']))
                                                <span>⏱ {{ gmdate('H:i:s', $plate['print_time_seconds']) }}</span>
                                            @endif
                                            @if(!empty($plate['filament_used_g']))
                                                <span>🧵 {{ $plate['filament_used_g'] }}g</span>
                                            @endif
                                        </div>

                                        {{-- Filamenty --}}
                                        @if(!empty($plate['filaments']))
                                            <div class="flex items-center flex-wrap gap-2 mt-2">
                                                @foreach($plate['filaments'] as $filament)
                                                    <div class="flex items-center space-x-1">
                                                        <div class="w-4 h-4 rounded-full border border-gray-200 shrink-0"
                                                            style="background-color: {{ $filament['color'] }}"></div>
                                                        <span class="text-xs text-gray-500">{{ $filament['type'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                </div>
                            @endforeach
                        </div>
                    @endif

                </div>
            </div>
        </div>
    @endif
</div>
