<div class="space-y-6">

    {{-- ================= Verze ================= --}}
    <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-bambu-dark-4 flex items-center justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-800 dark:text-bambu-text">Verze appky</h3>
                <p class="text-xs text-gray-500 dark:text-bambu-text-dim mt-0.5">
                    Nainstalováno: <span class="font-mono font-semibold text-gray-700 dark:text-bambu-text">{{ $this->currentVersion }}</span>
                    · přejít jde na verze od {{ config('bambups.min_switch_version') }}
                </p>
            </div>
            <button wire:click="refreshReleases" wire:loading.attr="disabled" wire:target="refreshReleases"
                class="px-3 py-1.5 text-xs bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 dark:hover:bg-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg shrink-0 disabled:opacity-50">
                🔄 Načíst znovu
            </button>
        </div>

        @php
            $releases = $this->releases;
            $us = new \App\Services\UpdateService();
        @endphp

        @if(count($releases) === 0)
            <p class="px-5 py-6 text-sm text-gray-400 dark:text-bambu-text-dim">
                Seznam verzí se nepodařilo načíst z GitHubu (chybí internet nebo je vyčerpaný limit API). Zkus to za pár minut znovu.
            </p>
        @else
            <div class="divide-y divide-gray-100 dark:divide-bambu-dark-4">
                @foreach($releases as $r)
                    @php
                        $cmp       = $us->compareVersions($r['tag'], $this->currentVersion);
                        $isCurrent = $r['tag'] === $this->currentVersion;
                    @endphp
                    <div wire:key="rel-{{ $r['tag'] }}" x-data="{ open: {{ $isCurrent ? 'true' : 'false' }} }" class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <button type="button" @click="open = !open" class="flex-1 min-w-0 text-left flex items-center gap-2">
                                <span class="text-gray-400 dark:text-bambu-text-dim text-xs transition-transform" :class="open ? 'rotate-90' : ''">▶</span>
                                <span class="font-mono text-sm font-semibold text-gray-800 dark:text-bambu-text">{{ $r['tag'] }}</span>
                                @if($r['name'] !== $r['tag'])
                                    <span class="text-sm text-gray-600 dark:text-bambu-text-dim truncate">{{ \Illuminate\Support\Str::after($r['name'], $r['tag']) ?: $r['name'] }}</span>
                                @endif
                                @if($r['prerelease'])
                                    <span class="px-1.5 py-0.5 text-xs rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 shrink-0">pre-release</span>
                                @endif
                            </button>
                            <span class="text-xs text-gray-400 dark:text-bambu-text-dim shrink-0 hidden sm:inline">
                                {{ $r['published_at'] ? \Carbon\Carbon::parse($r['published_at'])->timezone(config('app.timezone'))->format('d.m.Y') : '' }}
                            </span>

                            @if($isCurrent)
                                <span class="px-2.5 py-1 text-xs rounded-lg font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-bambu-green shrink-0">✓ Nainstalováno</span>
                            @elseif($cmp === 1)
                                <button wire:click="switchTo('{{ $r['tag'] }}')"
                                    wire:confirm="Aktualizovat na {{ $r['tag'] }}? Před aktualizací se automaticky zazálohuje databáze."
                                    class="px-2.5 py-1 text-xs rounded-lg font-medium bg-green-600 hover:bg-green-700 text-white shrink-0">⬆️ Aktualizovat</button>
                            @else
                                <button wire:click="switchTo('{{ $r['tag'] }}')"
                                    wire:confirm="Vrátit se na starší verzi {{ $r['tag'] }}?&#10;&#10;Databáze se před přechodem zazálohuje, data z novějších funkcí zůstanou zachovaná a znovu se objeví po návratu na novou verzi. Funkce přidané po {{ $r['tag'] }} nebudou dostupné."
                                    class="px-2.5 py-1 text-xs rounded-lg font-medium bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 dark:hover:bg-bambu-dark-4 text-gray-700 dark:text-bambu-text shrink-0">↩️ Vrátit se</button>
                            @endif
                        </div>

                        @if(trim($r['body']) !== '')
                            <div x-show="open" x-cloak x-collapse
                                class="mt-2 ml-5 prose prose-sm dark:prose-invert max-w-none text-gray-600 dark:text-bambu-text-dim">
                                {!! \Illuminate\Support\Str::markdown($r['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ================= Zálohy ================= --}}
    <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-bambu-dark-4 flex items-center justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-800 dark:text-bambu-text">Zálohy databáze</h3>
                <p class="text-xs text-gray-500 dark:text-bambu-text-dim mt-0.5">
                    Vytváří se automaticky před každou změnou verze a před obnovou. Uchovává se posledních {{ \App\Services\BackupService::MAX_BACKUPS }}.
                    Soubory 3D modelů se nezálohují, jen databáze.
                </p>
            </div>
            <button wire:click="createBackup" wire:loading.attr="disabled" wire:target="createBackup"
                class="px-3 py-1.5 text-xs bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium shrink-0 disabled:opacity-50">
                <span wire:loading.remove wire:target="createBackup">💾 Zálohovat teď</span>
                <span wire:loading wire:target="createBackup">Zálohuji...</span>
            </button>
        </div>

        @if(count($this->backups) === 0)
            <p class="px-5 py-6 text-sm text-gray-400 dark:text-bambu-text-dim">Zatím žádné zálohy.</p>
        @else
            <div class="divide-y divide-gray-100 dark:divide-bambu-dark-4">
                @foreach($this->backups as $b)
                    <div wire:key="bak-{{ $b['name'] }}" class="px-5 py-2.5 flex items-center gap-3 text-sm">
                        <span class="text-gray-700 dark:text-bambu-text w-32 shrink-0">{{ $b['created_at']->format('d.m.Y H:i') }}</span>
                        <span class="font-mono text-xs text-gray-500 dark:text-bambu-text-dim shrink-0 w-20 truncate" title="Verze appky v době zálohy">{{ $b['version'] }}</span>
                        <span class="text-xs text-gray-500 dark:text-bambu-text-dim flex-1 truncate">{{ $b['reason'] }}</span>
                        <span class="text-xs text-gray-400 dark:text-bambu-text-dim shrink-0 hidden sm:inline">{{ number_format($b['size'] / 1024, 0, ',', ' ') }} kB</span>
                        <a href="{{ route('versions.backup.download', $b['name']) }}" title="Stáhnout"
                            class="p-1 text-gray-400 hover:text-green-600 text-xs shrink-0">⬇️</a>
                        <button wire:click="askRestore('{{ $b['name'] }}')" title="Obnovit"
                            class="px-2 py-0.5 text-xs rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 hover:bg-amber-200 shrink-0">Obnovit</button>
                        <button wire:click="deleteBackup('{{ $b['name'] }}')" wire:confirm="Smazat zálohu z {{ $b['created_at']->format('d.m.Y H:i') }}?" title="Smazat"
                            class="p-1 text-gray-300 dark:text-bambu-dark-5 hover:text-red-600 text-xs shrink-0">🗑️</button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Potvrzení obnovy --}}
    @if($confirmRestore)
        @php $rb = collect($this->backups)->firstWhere('name', $confirmRestore); @endphp
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-bambu-dark-2 rounded-xl shadow-xl p-6 w-full max-w-md mx-4 border border-gray-100 dark:border-bambu-dark-4">
                <h3 class="font-semibold text-gray-900 dark:text-bambu-text mb-2">Obnovit databázi ze zálohy?</h3>
                <p class="text-sm text-gray-600 dark:text-bambu-text-dim mb-3">
                    Databáze se vrátí do stavu z <strong>{{ $rb ? $rb['created_at']->format('d.m.Y H:i') : '?' }}</strong>.
                    Všechno, co se změnilo od té doby (tiskárny, nastavení, historie tisku, záznamy o souborech), se přepíše.
                    Současný stav se předtím automaticky zazálohuje.
                </p>
                @if($rb && $rb['version'] !== $this->currentVersion)
                    <p class="text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded-lg px-3 py-2 mb-3">
                        ⚠️ Záloha je z verze {{ $rb['version'] }}, nainstalovaná je {{ $this->currentVersion }}. Chybějící tabulky se po obnově doplní migrací.
                    </p>
                @endif
                <p class="text-xs text-gray-400 dark:text-bambu-text-dim mb-4">Po obnově může být potřeba se znovu přihlásit.</p>

                @if($restoreError)
                    <pre class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-lg p-2 mb-3 whitespace-pre-wrap max-h-32 overflow-y-auto">{{ \Illuminate\Support\Str::limit($restoreError, 600) }}</pre>
                @endif

                <div class="flex space-x-3">
                    <button wire:click="cancelRestore" wire:loading.attr="disabled" wire:target="restore"
                        class="flex-1 px-4 py-2 border border-gray-300 dark:border-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg text-sm">Zrušit</button>
                    <button wire:click="restore" wire:loading.attr="disabled" wire:target="restore"
                        class="flex-1 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-medium disabled:opacity-50">
                        <span wire:loading.remove wire:target="restore">Obnovit</span>
                        <span wire:loading wire:target="restore">Obnovuji...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
