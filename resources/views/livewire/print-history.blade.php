<div class="p-6" wire:poll.15s.visible>

    {{-- Statistiky za zvolený filtr --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl p-4">
            <p class="text-xs text-gray-400 dark:text-bambu-text-dim">Tisků</p>
            <p class="text-2xl font-semibold text-gray-800 dark:text-bambu-text">{{ $s['count'] }}</p>
            <p class="text-xs text-gray-400 dark:text-bambu-text-dim mt-0.5">
                ✅ {{ $s['finished'] }} · ❌ {{ $s['failed'] }} · ⏹ {{ $s['cancelled'] }}
            </p>
        </div>
        <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl p-4">
            <p class="text-xs text-gray-400 dark:text-bambu-text-dim">Úspěšnost</p>
            <p class="text-2xl font-semibold text-gray-800 dark:text-bambu-text">{{ $s['success_rate'] !== null ? $s['success_rate'] . ' %' : '–' }}</p>
            <p class="text-xs text-gray-400 dark:text-bambu-text-dim mt-0.5">z ukončených tisků</p>
        </div>
        <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl p-4">
            <p class="text-xs text-gray-400 dark:text-bambu-text-dim">Čas tisku</p>
            <p class="text-2xl font-semibold text-gray-800 dark:text-bambu-text">{{ number_format($s['hours'], 1, ',', ' ') }} h</p>
            <p class="text-xs text-gray-400 dark:text-bambu-text-dim mt-0.5">včetně nedokončených</p>
        </div>
        <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl p-4">
            <p class="text-xs text-gray-400 dark:text-bambu-text-dim">Filament</p>
            <p class="text-2xl font-semibold text-gray-800 dark:text-bambu-text">{{ number_format($s['filament_kg'], 2, ',', ' ') }} kg</p>
            <p class="text-xs text-gray-400 dark:text-bambu-text-dim mt-0.5">odhad ze slicéru</p>
        </div>
    </div>

    {{-- Filtry --}}
    <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl p-3 mb-4 flex flex-wrap items-center gap-2">
        @if($fileFilter !== '')
            <span class="flex items-center gap-1.5 px-2.5 py-1 bg-green-50 dark:bg-bambu-dark-3 text-green-700 dark:text-bambu-green rounded-lg text-xs font-medium max-w-full">
                <span class="truncate">📄 {{ $this->filteredFile?->original_name ?? 'soubor #' . $fileFilter }}</span>
                <button wire:click="$set('fileFilter', '')" title="Zrušit filtr souboru" class="hover:text-red-600">✕</button>
            </span>
        @endif
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="🔍 Hledat podle názvu..."
            class="border border-gray-200 dark:border-bambu-dark-4 rounded-lg px-3 py-1.5 text-sm flex-1 min-w-[180px] dark:bg-bambu-dark-3 dark:text-bambu-text">

        <select wire:model.live="printerFilter"
            class="border border-gray-200 dark:border-bambu-dark-4 rounded-lg pl-3 pr-8 py-1.5 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
            <option value="">Všechny tiskárny</option>
            @foreach($this->printers as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="statusFilter"
            class="border border-gray-200 dark:border-bambu-dark-4 rounded-lg pl-3 pr-8 py-1.5 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
            <option value="">Všechny výsledky</option>
            <option value="running">Tiskne se</option>
            <option value="finished">Dokončeno</option>
            <option value="failed">Selhalo</option>
            <option value="cancelled">Zrušeno</option>
        </select>

        <select wire:model.live="period"
            class="border border-gray-200 dark:border-bambu-dark-4 rounded-lg pl-3 pr-8 py-1.5 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
            <option value="7">Posledních 7 dní</option>
            <option value="30">Posledních 30 dní</option>
            <option value="90">Posledních 90 dní</option>
            <option value="365">Poslední rok</option>
            <option value="all">Vše</option>
        </select>
    </div>

    {{-- Seznam --}}
    @if($jobs->count() > 0)
        <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden">
            <div class="divide-y divide-gray-100 dark:divide-bambu-dark-4">
                @foreach($jobs as $job)
                    @php
                        $badge = match ($job->status) {
                            'finished'  => 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-bambu-green',
                            'failed'    => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
                            'cancelled' => 'bg-gray-100 dark:bg-bambu-dark-3 text-gray-600 dark:text-bambu-text-dim',
                            'running'   => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
                            default     => 'bg-gray-100 dark:bg-bambu-dark-3 text-gray-600 dark:text-bambu-text-dim',
                        };
                        $dur = $job->duration;
                        $fil = $job->filament_used_g;
                        $hmsCodes = collect($job->hms ?? [])->map(fn ($h) => \App\Services\HmsService::decode($h)['message'] ?? null)->filter()->unique();
                    @endphp
                    <div wire:key="job-{{ $job->id }}" class="px-4 py-3 flex items-center gap-4 hover:bg-gray-50 dark:hover:bg-bambu-dark-3 transition-colors">

                        {{-- Náhled: snímek z kamery na konci, jinak náhled desky --}}
                        <div class="w-16 h-12 shrink-0 rounded-lg overflow-hidden bg-gray-100 dark:bg-bambu-dark-3 flex items-center justify-center">
                            @if($job->thumbnail_url)
                                <a href="{{ $job->thumbnail_url }}" target="_blank" class="block w-full h-full">
                                    <img src="{{ $job->thumbnail_url }}" loading="lazy"
                                        class="w-full h-full {{ $job->snapshot_path ? 'object-cover' : 'object-contain' }}" alt="náhled">
                                </a>
                            @else
                                <span class="text-xl">🖨️</span>
                            @endif
                        </div>

                        {{-- Název + tiskárna --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 min-w-0">
                                @if($job->file && $job->file->user_id === auth()->id())
                                    <a href="{{ route('file.show', $job->file->id) }}" title="{{ $job->name }}"
                                        class="text-sm font-medium text-gray-800 dark:text-bambu-text truncate hover:underline">{{ $job->name }}</a>
                                @else
                                    <span title="{{ $job->name }}" class="text-sm font-medium text-gray-800 dark:text-bambu-text truncate">{{ $job->name }}</span>
                                @endif
                                @if($job->plate_index && ($job->file?->metadata['plate_count'] ?? 1) > 1)
                                    <span class="text-xs text-gray-400 dark:text-bambu-text-dim shrink-0">deska {{ $job->plate_index }}</span>
                                @endif
                            </div>
                            <div class="flex items-center flex-wrap gap-x-2 gap-y-0.5 mt-0.5 text-xs text-gray-400 dark:text-bambu-text-dim">
                                <span>🖨️ {{ $job->printer?->name ?? $job->printer_name ?? '–' }}</span>
                                <span>·</span>
                                <span title="{{ $job->started_at?->format('d.m.Y H:i:s') }}">{{ $job->started_at?->format('d.m.Y H:i') ?? '–' }}</span>
                                @if($job->source === 'external')
                                    <span>·</span><span title="Spuštěno mimo BambuPS (slicer, displej, Handy)">mimo appku</span>
                                @endif
                                @if($job->filaments)
                                    <span>·</span>
                                    <span class="flex items-center gap-0.5">
                                        @foreach($job->filaments as $f)
                                            <span class="w-2.5 h-2.5 rounded-full border border-black/10 dark:border-white/20" style="background: {{ $f['color'] ?? '#888' }}" title="{{ $f['type'] ?? '' }}"></span>
                                        @endforeach
                                    </span>
                                @endif
                            </div>
                            @if($job->status === 'failed' && $hmsCodes->isNotEmpty())
                                <p class="text-xs text-red-500 dark:text-red-400 mt-0.5 truncate" title="{{ $hmsCodes->implode(' | ') }}">⚠ {{ $hmsCodes->first() }}</p>
                            @endif
                        </div>

                        {{-- Čísla --}}
                        <div class="hidden md:block text-right text-xs shrink-0 w-28">
                            <p class="text-gray-700 dark:text-bambu-text">⏱ {{ \App\Models\PrintJob::formatSeconds($dur) }}</p>
                            @if($job->estimated_seconds)
                                <p class="text-gray-400 dark:text-bambu-text-dim">odhad {{ \App\Models\PrintJob::formatSeconds($job->estimated_seconds) }}</p>
                            @endif
                        </div>
                        <div class="hidden sm:block text-right text-xs shrink-0 w-20">
                            <p class="text-gray-700 dark:text-bambu-text">{{ $fil !== null ? (($job->status !== 'finished' ? '≈ ' : '') . number_format($fil, 1, ',', ' ') . ' g') : '–' }}</p>
                            @if($job->status !== 'finished' && $job->progress !== null)
                                <p class="text-gray-400 dark:text-bambu-text-dim">{{ $job->progress }} %
                                    @if($job->layer_reached && $job->total_layers) · {{ $job->layer_reached }}/{{ $job->total_layers }} @endif
                                </p>
                            @endif
                        </div>

                        {{-- Výsledek --}}
                        <span class="shrink-0 px-2 py-0.5 text-xs rounded-full font-medium {{ $badge }}">
                            @if($job->status === 'running')<span class="animate-pulse">●</span>@endif
                            {{ $job->status_label }}
                        </span>

                        @if(!$job->isOpen())
                            <button wire:click="confirmDelete({{ $job->id }})" title="Smazat z historie"
                                class="shrink-0 p-1 text-gray-300 dark:text-bambu-dark-5 hover:text-red-600 text-xs transition-colors">🗑️</button>
                        @else
                            <span class="shrink-0 w-6"></span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-4">
            {{ $jobs->links() }}
        </div>
    @else
        <div class="text-center py-16 text-gray-400 dark:text-bambu-text-dim">
            <p class="text-5xl mb-4">🕘</p>
            <p class="font-medium text-lg">Zatím žádné tisky</p>
            <p class="text-sm mt-2">
                @if($printerFilter !== '' || $statusFilter !== '' || $search !== '' || $period !== 'all' || $fileFilter !== '')
                    Pro zvolený filtr nic nenalezeno.
                @else
                    Tisky se sem začnou zapisovat automaticky, jakmile tiskárna začne tisknout.
                @endif
            </p>
        </div>
    @endif

    {{-- Potvrzení smazání --}}
    @if($confirmDeleteId)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-bambu-dark-2 rounded-xl shadow-xl p-6 w-full max-w-md mx-4 border border-gray-100 dark:border-bambu-dark-4">
                <h3 class="font-semibold text-gray-900 dark:text-bambu-text mb-1">Smazat záznam z historie?</h3>
                <p class="text-sm text-gray-500 dark:text-bambu-text-dim mb-5">Soubor ani tiskárny se to nijak nedotkne.</p>
                <div class="flex space-x-3">
                    <button wire:click="cancelDelete"
                        class="flex-1 px-4 py-2 border border-gray-300 dark:border-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg text-sm">Zrušit</button>
                    <button wire:click="deleteJob"
                        class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium">Smazat</button>
                </div>
            </div>
        </div>
    @endif

</div>
