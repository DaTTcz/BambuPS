<div class="p-6" wire:poll.5000ms>

    {{-- Tiskárny --}}
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-bambu-text">🖨️ Tiskárny</h2>
            <a href="{{ route('printers') }}" class="text-sm font-medium hover:underline">Všechny tiskárny →</a>
        </div>

        @if($this->printers->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($this->printers as $printer)
                    <x-printer-card :printer="$printer" />
                @endforeach
            </div>
        @else
            <div class="bg-white dark:bg-bambu-dark-2 border border-dashed border-gray-300 dark:border-bambu-dark-4 rounded-xl p-12 text-center text-gray-400">
                <p class="text-4xl mb-3">🖨️</p>
                <p class="font-medium">Žádné tiskárny</p>
                <p class="text-sm mt-1">
                    <a href="{{ route('printers.manage') }}" class="hover:underline">Přidat tiskárnu →</a>
                </p>
            </div>
        @endif
    </div>

    {{-- Nedávné soubory --}}
    <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-bambu-dark-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-bambu-text">📁 Nedávné soubory</h2>
            <a href="{{ route('files') }}" class="text-sm font-medium hover:underline">Zobrazit vše →</a>
        </div>

        @if($this->recentFiles->count() > 0)
            <div class="divide-y divide-gray-50 dark:divide-bambu-dark-4">
                @foreach($this->recentFiles as $file)
                    <a href="{{ route('file.show', $file->id) }}"
                        class="px-5 py-3.5 flex items-center space-x-4 hover:bg-gray-50 dark:hover:bg-bambu-dark-3 transition-colors block">

                        {{-- Thumbnail --}}
                        <div class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100 dark:bg-bambu-dark-3 shrink-0 flex items-center justify-center">
                            @if($file->thumbnail_path)
                                <img src="{{ route('file.thumbnail', $file->id) }}"
                                    class="w-full h-full object-contain" alt="náhled">
                            @elseif(str_ends_with(strtolower($file->original_name), '.gcode'))
                                <img src="/images/gcode-placeholder.png" class="w-full h-full object-contain p-1 opacity-70">
                            @else
                                <span class="text-xl">🗂️</span>
                            @endif
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 dark:text-bambu-text truncate">{{ $file->original_name }}</p>
                            <div class="flex items-center space-x-2 mt-0.5 text-xs text-gray-400 dark:text-bambu-text-dim">
                                <span>{{ $file->size_formatted }}</span>
                                @if(!empty($file->metadata['print_time']))
                                    <span>·</span><span>⏱ {{ $file->metadata['print_time'] }}</span>
                                @endif
                                @if(!empty($file->metadata['filament_used_g']))
                                    <span>·</span><span>🧵 {{ $file->metadata['filament_used_g'] }}g</span>
                                @endif
                                @if(!empty($file->metadata['plate_count']) && $file->metadata['plate_count'] > 1)
                                    <span>·</span><span>{{ $file->metadata['plate_count'] }}× podložka</span>
                                @endif
                            </div>
                        </div>

                        {{-- Right --}}
                        <div class="flex items-center space-x-2 shrink-0">
                            <span class="text-xs text-gray-400 dark:text-bambu-text-dim hidden sm:block">
                                {{ $file->created_at->diffForHumans() }}
                            </span>
                            @if(!empty($file->metadata['has_gcode']) && $file->metadata['has_gcode'])
                                <span class="px-2 py-0.5 bg-green-100 dark:bg-bambu-dark-3 text-green-700 dark:text-bambu-green text-xs rounded-full font-medium">G-code</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="px-6 py-16 text-center text-gray-400 dark:text-bambu-text-dim">
                <p class="text-4xl mb-3">🗂️</p>
                <p class="font-medium">Žádné soubory</p>
                <p class="text-sm mt-1">Nahraj první soubor v sekci
                    <a href="{{ route('files') }}" class="hover:underline">Soubory</a>
                </p>
            </div>
        @endif
    </div>

</div>
