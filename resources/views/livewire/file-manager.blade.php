<div class="p-6"
    x-data="{}"
    wire:poll.10s.visible
    @save-fm-prefs.window="localStorage.setItem('fm_prefs', JSON.stringify($event.detail[0]))">

    {{-- Statistiky + Toolbar --}}
    <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl p-4 mb-4">

        {{-- Statistiky --}}
        <div class="flex items-center space-x-4 mb-3 text-xs text-gray-500 dark:text-bambu-text-dim">
            <span>📁 <strong class="text-gray-700 dark:text-bambu-text">{{ $this->stats['total_folders'] }}</strong> složek</span>
            <span class="text-gray-300 dark:text-bambu-dark-4">|</span>
            <span>🗂️ <strong class="text-gray-700 dark:text-bambu-text">{{ $this->stats['total_files'] }}</strong> souborů</span>
            <span class="text-gray-300 dark:text-bambu-dark-4">|</span>
            <span>⚙️ <strong class="text-gray-700 dark:text-bambu-text">{{ $this->stats['gcode_files'] }}</strong> G-code</span>
            <span class="text-gray-300 dark:text-bambu-dark-4">|</span>
            <span>💾 <strong class="text-gray-700 dark:text-bambu-text">{{ $this->stats['total_size'] }}</strong></span>
        </div>

        {{-- Breadcrumbs --}}
        <nav class="flex items-center space-x-1.5 text-sm mb-3">
            <button wire:click="goToFolder(null)"
                class="flex items-center space-x-1.5 px-3 py-1.5 bg-green-50 dark:bg-bambu-dark-3 hover:bg-green-100 dark:hover:bg-bambu-dark-4 text-green-700 dark:text-bambu-green rounded-lg font-medium transition-colors text-xs">
                <span>🏠</span><span>Domů</span>
            </button>
            @foreach($this->breadcrumbs as $crumb)
                <span class="text-gray-300 dark:text-bambu-dark-5">/</span>
                <button wire:click="goToFolder({{ $crumb->id }})"
                    class="px-2 py-1 text-xs text-gray-600 dark:text-bambu-text-dim hover:bg-gray-100 dark:hover:bg-bambu-dark-3 rounded-lg">
                    {{ $crumb->name }}
                </button>
            @endforeach
        </nav>

	{{-- Toolbar --}}
        <div class="space-y-2">
            {{-- Řádek 1: View toggle, hledání, akce --}}
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center space-x-2 flex-1 min-w-0">
                    <button wire:click="toggleView"
                        class="px-3 py-1.5 text-xs bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 dark:hover:bg-bambu-dark-4 text-gray-600 dark:text-bambu-text-dim rounded-lg shrink-0 transition-colors">
                        {{ $viewMode === 'card' ? '☰ Řádky' : '⊞ Karty' }}
                    </button>
                    <input wire:model.live="search" type="text" placeholder="🔍 Hledat..."
                        class="border border-gray-200 dark:border-bambu-dark-4 rounded-lg px-3 py-1.5 text-sm flex-1 min-w-0 dark:bg-bambu-dark-3 dark:text-bambu-text">
                </div>
                <div class="flex items-center space-x-2 shrink-0">
                    <button wire:click="$set('showNewFolderModal', true)"
                        class="hidden sm:block px-3 py-1.5 text-xs bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 dark:hover:bg-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg transition-colors">
                        📁 Nová složka
                    </button>
                    <button wire:click="$set('showUploadModal', true)"
                        class="px-4 py-1.5 text-xs bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">
                        ⬆ Nahrát
                    </button>
                </div>
            </div>
            {{-- Řádek 2: Řazení + Nová složka na mobilu --}}
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center space-x-1.5">
                    <button wire:click="setSort('original_name')"
                        class="px-3 py-1.5 text-xs rounded-lg transition-colors {{ $sortBy === 'original_name' ? 'bg-green-600 text-white' : 'bg-gray-100 dark:bg-bambu-dark-3 text-gray-600 dark:text-bambu-text-dim hover:bg-gray-200 dark:hover:bg-bambu-dark-4' }}">
                        Název {{ $sortBy === 'original_name' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                    </button>
                    <button wire:click="setSort('created_at')"
                        class="px-3 py-1.5 text-xs rounded-lg transition-colors {{ $sortBy === 'created_at' ? 'bg-green-600 text-white' : 'bg-gray-100 dark:bg-bambu-dark-3 text-gray-600 dark:text-bambu-text-dim hover:bg-gray-200 dark:hover:bg-bambu-dark-4' }}">
                        Datum {{ $sortBy === 'created_at' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                    </button>
                    <button wire:click="setSort('size_bytes')"
                        class="px-3 py-1.5 text-xs rounded-lg transition-colors {{ $sortBy === 'size_bytes' ? 'bg-green-600 text-white' : 'bg-gray-100 dark:bg-bambu-dark-3 text-gray-600 dark:text-bambu-text-dim hover:bg-gray-200 dark:hover:bg-bambu-dark-4' }}">
                        Velikost {{ $sortBy === 'size_bytes' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                    </button>
                </div>
                <button wire:click="$set('showNewFolderModal', true)"
                    class="sm:hidden px-3 py-1.5 text-xs bg-gray-100 dark:bg-bambu-dark-3 text-gray-700 dark:text-bambu-text rounded-lg transition-colors">
                    📁 Nová složka
                </button>
            </div>
        </div>
    </div>

    {{-- Složky --}}
    @if($this->folders->count() > 0)
        <div class="mb-4">
            <p class="text-xs font-medium text-gray-400 dark:text-bambu-text-dim uppercase tracking-wide mb-2">Složky</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2">
                @foreach($this->folders as $folder)
                    <div class="group relative bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl p-3 hover:border-green-400 dark:hover:border-bambu-green cursor-pointer transition-all"
                        wire:click="openFolder({{ $folder->id }})">
                        <div class="flex items-center space-x-2">
                            <span class="text-xl">📁</span>
                            <span class="text-sm font-medium text-gray-700 dark:text-bambu-text truncate">{{ $folder->name }}</span>
                        </div>
                        <div class="absolute top-1 right-1 hidden group-hover:flex space-x-0.5">
                            <button onclick="event.stopPropagation()" wire:click="startMove('folder', {{ $folder->id }}, '{{ addslashes($folder->name) }}')"
                                class="p-1 bg-white dark:bg-bambu-dark-3 rounded shadow text-gray-400 hover:text-green-600 text-xs">📂</button>
                            <button onclick="event.stopPropagation()" wire:click="startRename('folder', {{ $folder->id }}, '{{ addslashes($folder->name) }}')"
                                class="p-1 bg-white dark:bg-bambu-dark-3 rounded shadow text-gray-400 hover:text-green-600 text-xs">✏️</button>
                            <button onclick="event.stopPropagation()" wire:click="confirmDeleteFolder({{ $folder->id }})"
                                class="p-1 bg-white dark:bg-bambu-dark-3 rounded shadow text-gray-400 hover:text-red-600 text-xs">🗑️</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Soubory --}}
    @if($this->files->count() > 0)
        <p class="text-xs font-medium text-gray-400 dark:text-bambu-text-dim uppercase tracking-wide mb-2">Soubory</p>

        @if($viewMode === 'card')
            {{-- Card view --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                @foreach($this->files as $file)
		    <div class="group relative bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden hover:border-green-400 dark:hover:border-bambu-green hover:shadow-sm transition-all cursor-pointer"
                        onclick="window.location='{{ route('file.show', $file->id) }}'">

			<div class="aspect-square relative">
                            @if($file->list_thumbnail_url)
                                <img src="{{ $file->list_thumbnail_url }}" class="w-full h-full object-contain bg-gray-50 dark:bg-bambu-dark-3" alt="náhled">
                            @elseif(str_ends_with(strtolower($file->original_name), '.gcode'))
                                <img src="/images/gcode-placeholder.png" class="w-full h-full object-cover" alt="gcode">
                            @else
                                <div class="w-full h-full bg-gray-50 dark:bg-bambu-dark-3 flex items-center justify-center">
                                    <span class="text-3xl">🗂️</span>
                                </div>
                            @endif
                        </div>

                        <div class="p-2">
                            <p class="text-xs font-medium text-gray-800 dark:text-bambu-text truncate">{{ $file->original_name }}</p>
                            <p class="text-xs text-gray-400 dark:text-bambu-text-dim">{{ $file->size_formatted }}</p>
                            @if(!empty($file->metadata['print_time']))
                                <p class="text-xs text-gray-500 dark:text-bambu-text-dim">⏱ {{ $file->metadata['print_time'] }}</p>
                            @endif
                            <div class="flex items-center gap-1 mt-1 flex-wrap">
                                @if(!empty($file->metadata['has_gcode']) && $file->metadata['has_gcode'])
                                    <span class="px-1.5 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-bambu-green text-xs rounded-full">G</span>
                                @endif
                                @if(!empty($file->metadata['plate_count']) && $file->metadata['plate_count'] > 1)
                                    <span class="px-1.5 py-0.5 bg-blue-100 dark:bg-bambu-dark-3 text-blue-700 dark:text-bambu-text-dim text-xs rounded-full">{{ $file->metadata['plate_count'] }}×</span>
                                @endif
                            </div>
                        </div>

			{{-- Akční ikonky --}}
                        <div class="border-t border-gray-100 dark:border-bambu-dark-4 px-2 py-1.5 flex items-center justify-between">
                            <a onclick="event.stopPropagation()" href="{{ route('file.download', $file->id) }}"
                                title="Stáhnout" class="p-1 text-gray-400 hover:text-green-600 text-xs transition-colors">⬇️</a>
                            <button onclick="event.stopPropagation()" wire:click="reparseFile({{ $file->id }})"
                                title="Znovu parsovat" class="p-1 text-gray-400 hover:text-purple-500 text-xs transition-colors">🔄</button>
                            <button onclick="event.stopPropagation()" wire:click="startMove('file', {{ $file->id }}, '{{ addslashes($file->original_name) }}')"
                                title="Přesunout" class="p-1 text-gray-400 hover:text-green-600 text-xs transition-colors">📂</button>
                            <button onclick="event.stopPropagation()" wire:click="startRename('file', {{ $file->id }}, '{{ addslashes($file->original_name) }}')"
                                title="Přejmenovat" class="p-1 text-gray-400 hover:text-green-600 text-xs transition-colors">✏️</button>
                            <button onclick="event.stopPropagation()" wire:click="confirmDelete({{ $file->id }})"
                                title="Smazat" class="p-1 text-gray-400 hover:text-red-600 text-xs transition-colors">🗑️</button>
                        </div>

                    </div>
                @endforeach
            </div>

        @else
            {{-- List view --}}
            <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-bambu-dark-3 border-b border-gray-100 dark:border-bambu-dark-4">
                        <tr>
                            <th class="text-left px-4 py-2.5 text-xs font-medium text-gray-500 dark:text-bambu-text-dim">Název</th>
                            <th class="text-left px-4 py-2.5 text-xs font-medium text-gray-500 dark:text-bambu-text-dim hidden sm:table-cell">Čas tisku</th>
                            <th class="text-left px-4 py-2.5 text-xs font-medium text-gray-500 dark:text-bambu-text-dim hidden md:table-cell">Filament</th>
                            <th class="text-left px-4 py-2.5 text-xs font-medium text-gray-500 dark:text-bambu-text-dim hidden lg:table-cell">Velikost</th>
                            <th class="text-left px-4 py-2.5 text-xs font-medium text-gray-500 dark:text-bambu-text-dim hidden lg:table-cell">Datum</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-bambu-dark-4">
                        @foreach($this->files as $file)
                            <tr class="hover:bg-gray-50 dark:hover:bg-bambu-dark-3 cursor-pointer transition-colors"
                                onclick="window.location='{{ route('file.show', $file->id) }}'">
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-3">
                                        {{-- Thumbnail s tooltip --}}
                                        <div class="relative shrink-0"
                                            x-data="{ show: false }"
                                            @mouseenter="show = true"
                                            @mouseleave="show = false">
                                            <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-bambu-dark-3 overflow-hidden flex items-center justify-center">
                                                @if($file->list_thumbnail_url)
                                                    <img src="{{ $file->list_thumbnail_url }}" class="w-full h-full object-contain" alt="náhled">
                                                @elseif(str_ends_with(strtolower($file->original_name), '.gcode'))
                                                    <img src="/images/gcode-placeholder.png" class="w-full h-full object-contain p-1 rounded-t-xl">
                                                @else
                                                    <span>🗂️</span>
                                                @endif
                                            </div>
                                            {{-- Tooltip --}}
                                            @if($file->list_thumbnail_url || str_ends_with(strtolower($file->original_name), '.gcode'))
                                                <div x-show="show" x-transition
                                                    class="absolute left-12 top-0 z-50 w-48 h-48 rounded-xl overflow-hidden shadow-xl border border-gray-200 dark:border-bambu-dark-4 bg-white dark:bg-bambu-dark-2 pointer-events-none">
                                                    @if($file->list_thumbnail_url)
                                                        <img src="{{ $file->list_thumbnail_url }}" class="w-full h-full object-contain p-2">
                                                    @else
                                                        <img src="/images/gcode-placeholder.png" class="w-full h-full object-contain p-4 rounded-t-xl">
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-medium text-gray-800 dark:text-bambu-text truncate">{{ $file->original_name }}</p>
                                            <div class="flex items-center gap-1 mt-0.5">
                                                @if(!empty($file->metadata['has_gcode']) && $file->metadata['has_gcode'])
                                                    <span class="px-1.5 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-bambu-green text-xs rounded-full">G-code</span>
                                                @endif
                                                @if(!empty($file->metadata['plate_count']) && $file->metadata['plate_count'] > 1)
                                                    <span class="px-1.5 py-0.5 bg-gray-100 dark:bg-bambu-dark-3 text-gray-600 dark:text-bambu-text-dim text-xs rounded-full">{{ $file->metadata['plate_count'] }} podložky</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-bambu-text-dim text-xs hidden sm:table-cell">
                                    {{ $file->metadata['print_time'] ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-bambu-text-dim text-xs hidden md:table-cell">
                                    {{ !empty($file->metadata['filament_used_g']) ? $file->metadata['filament_used_g'] . 'g' : '-' }}
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-bambu-text-dim text-xs hidden lg:table-cell">
                                    {{ $file->size_formatted }}
                                </td>
                                <td class="px-4 py-3 text-gray-400 dark:text-bambu-text-dim text-xs hidden lg:table-cell">
                                    {{ $file->created_at->format('d.m.Y') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-1 justify-end">
                                        <a onclick="event.stopPropagation()" href="{{ route('file.download', $file->id) }}"
                                            title="Stáhnout soubor" class="p-1 text-gray-400 hover:text-green-600 text-xs">⬇️</a>
                                        <button onclick="event.stopPropagation()" wire:click="reparseFile({{ $file->id }})"
                                            title="Znovu parsovat metadata" class="p-1 text-gray-400 hover:text-purple-600 text-xs">🔄</button>
                                        <button onclick="event.stopPropagation()" wire:click="startMove('file', {{ $file->id }}, '{{ addslashes($file->original_name) }}')"
                                            title="Přesunout do složky" class="p-1 text-gray-400 hover:text-green-600 text-xs">📂</button>
                                        <button onclick="event.stopPropagation()" wire:click="startRename('file', {{ $file->id }}, '{{ addslashes($file->original_name) }}')"
                                            title="Přejmenovat" class="p-1 text-gray-400 hover:text-green-600 text-xs">✏️</button>
                                        <button onclick="event.stopPropagation()" wire:click="confirmDelete({{ $file->id }})"
                                            title="Smazat soubor" class="p-1 text-gray-400 hover:text-red-600 text-xs">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    {{-- Prázdný stav --}}
    @if($this->folders->count() === 0 && $this->files->count() === 0)
        <div class="text-center py-16 text-gray-400 dark:text-bambu-text-dim">
            <p class="text-5xl mb-4">🗂️</p>
            <p class="font-medium text-lg">
                {{ $search ? 'Nic nenalezeno pro "' . $search . '"' : 'Složka je prázdná' }}
            </p>
            @if(!$search)
                <p class="text-sm mt-2">Nahraj soubory nebo vytvoř novou složku</p>
            @endif
        </div>
    @endif

    {{-- Modal: Nová složka --}}
    @if($showNewFolderModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-bambu-dark-2 rounded-xl shadow-xl p-6 w-full max-w-md mx-4 border border-gray-100 dark:border-bambu-dark-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-bambu-text mb-4">📁 Nová složka</h3>
                <input wire:model="newFolderName" type="text" placeholder="Název složky"
                    class="w-full border border-gray-200 dark:border-bambu-dark-4 rounded-lg px-4 py-2 text-sm mb-4 dark:bg-bambu-dark-3 dark:text-bambu-text">
                <div class="flex justify-end space-x-3">
                    <button wire:click="$set('showNewFolderModal', false)"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-bambu-text-dim hover:text-gray-800 dark:hover:text-bambu-text">Zrušit</button>
                    <button wire:click="createFolder"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium">Vytvořit</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Upload --}}
    @if($showUploadModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-bambu-dark-2 rounded-xl shadow-xl p-6 w-full max-w-md mx-4 border border-gray-100 dark:border-bambu-dark-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-bambu-text mb-4">⬆ Nahrát soubory</h3>
                <input wire:model="uploadedFiles" type="file" multiple accept=".3mf,.gcode"
                    class="w-full text-sm text-gray-500 dark:text-bambu-text-dim mb-4">
                <div wire:loading wire:target="uploadedFiles" class="text-sm text-green-600 mb-2">Nahrávám...</div>
                <div class="flex justify-end space-x-3">
                    <button wire:click="$set('showUploadModal', false)"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-bambu-text-dim hover:text-gray-800">Zrušit</button>
                    <button wire:click="uploadFiles"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium">
                        <span wire:loading.remove wire:target="uploadFiles">Nahrát</span>
                        <span wire:loading wire:target="uploadFiles">Zpracovávám...</span>
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
                <input wire:model="renamingName" type="text"
                    class="w-full border border-gray-200 dark:border-bambu-dark-4 rounded-lg px-4 py-2 text-sm mb-4 dark:bg-bambu-dark-3 dark:text-bambu-text">
                <div class="flex justify-end space-x-3">
                    <button wire:click="$set('showRenameModal', false)"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-bambu-text-dim">Zrušit</button>
                    <button wire:click="rename"
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
                <p class="text-sm text-gray-500 dark:text-bambu-text-dim mb-4 truncate">{{ $movingName }}</p>
                <div class="space-y-1 max-h-64 overflow-y-auto mb-4">
                    <button wire:click="moveTo(null)"
                        class="w-full text-left px-3 py-2 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-bambu-dark-3 text-gray-700 dark:text-bambu-text flex items-center space-x-2">
                        <span>🏠</span><span>Kořenová složka</span>
                    </button>
                    @foreach($this->allFolders as $folder)
                        @if($folder->id !== $movingFolderId)
                            <button wire:click="moveTo({{ $folder->id }})"
                                class="w-full text-left px-3 py-2 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-bambu-dark-3 text-gray-700 dark:text-bambu-text flex items-center space-x-2">
                                <span>📁</span><span>{{ $folder->name }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>
                <div class="flex justify-end">
                    <button wire:click="cancelMove"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-bambu-text-dim">Zrušit</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Potvrzení smazání složky --}}
    @if($confirmDeleteFolderId)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-bambu-dark-2 rounded-xl shadow-xl p-6 w-full max-w-md mx-4 border border-gray-100 dark:border-bambu-dark-4">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center shrink-0">
                        <span class="text-red-600 text-xl">🗑️</span>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-bambu-text">Smazat složku?</h3>
                        <p class="text-sm text-gray-500 dark:text-bambu-text-dim mt-0.5">Složka musí být prázdná.</p>
                    </div>
                </div>
                <p class="text-sm text-gray-700 dark:text-bambu-text bg-gray-50 dark:bg-bambu-dark-3 rounded-lg px-3 py-2 mb-5 truncate">
                    📁 {{ $confirmDeleteFolderName }}
                </p>
                <div class="flex space-x-3">
                    <button wire:click="cancelDeleteFolder"
                        class="flex-1 px-4 py-2 border border-gray-300 dark:border-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg text-sm">Zrušit</button>
                    <button wire:click="deleteFolder"
                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium">Smazat</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Potvrzení mazání souboru --}}
    @if($confirmDeleteId)
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
                    📄 {{ $confirmDeleteName }}
                </p>
                <div class="flex space-x-3">
                    <button wire:click="cancelDelete"
                        class="flex-1 px-4 py-2 border border-gray-300 dark:border-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg text-sm">Zrušit</button>
                    <button wire:click="deleteFile"
                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium">Smazat</button>
                </div>
            </div>
        </div>
    @endif

    @script
    <script>
        const p = JSON.parse(localStorage.getItem('fm_prefs') || '{}');
        if (p.viewMode) $wire.set('viewMode', p.viewMode);
        if (p.sortBy)   $wire.set('sortBy',   p.sortBy);
        if (p.sortDir)  $wire.set('sortDir',  p.sortDir);
    </script>
    @endscript

</div>
