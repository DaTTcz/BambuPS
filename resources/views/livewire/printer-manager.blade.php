<div class="p-6">

    {{-- Toolbar --}}
    <div class="flex items-center justify-between mb-6">
        <h3 class="font-semibold text-gray-800 dark:text-bambu-text">Správa tiskáren</h3>
        <button wire:click="openCreate"
            class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm">
            + Přidat tiskárnu
        </button>
    </div>

    {{-- Seznam tiskáren --}}
    @if($this->printers->count() > 0)
        <div class="space-y-4">
            @foreach($this->printers as $printer)
                <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl p-5">
                    <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px;">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center
                                {{ $printer->enabled ? 'bg-green-100 dark:bg-green-900/30' : 'bg-gray-100 dark:bg-bambu-dark-3' }}">
                                <span class="text-2xl">🖨️</span>
                            </div>
                            <div>
                                <div class="flex items-center space-x-2">
                                    <p class="font-semibold text-gray-800 dark:text-bambu-text">{{ $printer->name }}</p>
                                    <span class="px-2 py-0.5 text-xs rounded-full
                                        {{ $printer->is_online
                                            ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-bambu-green'
                                            : 'bg-gray-100 dark:bg-bambu-dark-3 text-gray-500 dark:text-bambu-text-dim' }}">
                                        {{ $printer->is_online ? '● Online' : '○ Offline' }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-bambu-text-dim">{{ $printer->model }}</p>
                                <div class="flex items-center space-x-3 mt-1">
                                    <span class="text-xs text-gray-400 dark:text-bambu-text-dim font-mono">{{ $printer->ip_address }}</span>
                                    <span class="text-xs text-gray-400 dark:text-bambu-text-dim font-mono">SN: {{ $printer->serial_number }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 shrink-0">
                            <button wire:click="openEdit({{ $printer->id }})"
                                class="px-3 py-1.5 text-sm bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 dark:hover:bg-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg">
                                ✏️ Upravit
                            </button>
                            <button wire:click="toggleEnabled({{ $printer->id }})"
                                class="px-3 py-1.5 text-sm rounded-lg
                                    {{ $printer->enabled
                                        ? 'bg-red-100 dark:bg-red-900/30 hover:bg-red-200 text-red-700 dark:text-red-400'
                                        : 'bg-green-100 dark:bg-green-900/30 hover:bg-green-200 text-green-700 dark:text-bambu-green' }}">
                                {{ $printer->enabled ? 'Deaktivovat' : 'Aktivovat' }}
                            </button>
                            <button wire:click="delete({{ $printer->id }})"
                                wire:confirm="Opravdu smazat tiskárnu {{ $printer->name }}?"
                                class="px-3 py-1.5 text-sm bg-red-100 dark:bg-red-900/30 hover:bg-red-200 text-red-700 dark:text-red-400 rounded-lg">
                                🗑️ Smazat
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-16 text-gray-400 dark:text-bambu-text-dim">
            <p class="text-4xl mb-3">🖨️</p>
            <p class="font-medium">Žádné tiskárny</p>
            <p class="text-sm mt-1">Přidej první tiskárnu tlačítkem výše</p>
        </div>
    @endif

    {{-- Modal: Přidat / Upravit tiskárnu --}}
    @if($showModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-bambu-dark-2 border border-gray-100 dark:border-bambu-dark-4 rounded-xl shadow-xl p-6 w-full max-w-lg mx-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-bambu-text mb-5">
                    {{ $editing ? 'Upravit tiskárnu' : 'Přidat tiskárnu' }}
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-600 dark:text-bambu-text-dim mb-1">Název</label>
                        <input wire:model="name" type="text" placeholder="Např. Bambu X1C - Dílna"
                            class="w-full border border-gray-300 dark:border-bambu-dark-4 rounded-lg px-4 py-2 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600 dark:text-bambu-text-dim mb-1">Model</label>
                        <select wire:model="model"
                            class="w-full border border-gray-300 dark:border-bambu-dark-4 rounded-lg px-4 py-2 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
                            <option value="">-- Vyber model --</option>
                            <option value="Bambu Lab X1 Carbon">Bambu Lab X1 Carbon</option>
                            <option value="Bambu Lab X1E">Bambu Lab X1E</option>
                            <option value="Bambu Lab P1S">Bambu Lab P1S</option>
                            <option value="Bambu Lab P1P">Bambu Lab P1P</option>
                            <option value="Bambu Lab A1">Bambu Lab A1</option>
                            <option value="Bambu Lab A1 Mini">Bambu Lab A1 Mini</option>
                        </select>
                        @error('model') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600 dark:text-bambu-text-dim mb-1">Sériové číslo</label>
                        <input wire:model="serial_number" type="text" placeholder="01S00C123456789"
                            class="w-full border border-gray-300 dark:border-bambu-dark-4 rounded-lg px-4 py-2 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
                        @error('serial_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600 dark:text-bambu-text-dim mb-1">IP adresa</label>
                        <input wire:model="ip_address" type="text" placeholder="192.168.1.100"
                            class="w-full border border-gray-300 dark:border-bambu-dark-4 rounded-lg px-4 py-2 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
                        @error('ip_address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-gray-600 dark:text-bambu-text-dim mb-1">Přístupový kód</label>
                        <input wire:model="access_code" type="password" placeholder="Access Code z tiskárny"
                            class="w-full border border-gray-300 dark:border-bambu-dark-4 rounded-lg px-4 py-2 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
                        @error('access_code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button wire:click="$set('showModal', false)"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-bambu-text-dim hover:text-gray-800 dark:hover:text-bambu-text">Zrušit</button>
                    <button wire:click="save"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium">
                        {{ $editing ? 'Uložit změny' : 'Přidat tiskárnu' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Live progress checklist po uložení --}}
    @if($showProvisionModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
            x-data="{
                steps: @js(array_keys($provisionSteps)),
                current: 0,
                async run() {
                    for (let i = 0; i < this.steps.length; i++) {
                        this.current = i;
                        await $wire.runProvisionStep(this.steps[i]);
                    }
                    this.current = this.steps.length;
                    await $wire.finishProvisioning();
                }
            }"
            x-init="run()">
            <div class="bg-white dark:bg-bambu-dark-2 border border-gray-100 dark:border-bambu-dark-4 rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-bambu-text mb-5">
                    Nastavuji kameru tiskárny...
                </h3>

                <ul class="space-y-3">
                    @foreach($provisionSteps as $key => $label)
                        <li class="flex items-center space-x-3 text-sm" wire:key="provision-step-{{ $key }}">
                            @if(($provisionStatus[$key] ?? 'pending') === 'done')
                                <span class="text-green-600 dark:text-bambu-green shrink-0">✅</span>
                            @else
                                <span x-show="steps[current] === '{{ $key }}'"
                                    class="shrink-0 inline-block w-4 h-4 border-2 border-gray-300 dark:border-bambu-dark-4 border-t-green-600 dark:border-t-bambu-green rounded-full animate-spin"></span>
                                <span x-show="steps[current] !== '{{ $key }}'"
                                    class="shrink-0 text-gray-300 dark:text-bambu-text-dim">○</span>
                            @endif
                            <span class="{{ ($provisionStatus[$key] ?? 'pending') === 'done'
                                ? 'text-gray-800 dark:text-bambu-text'
                                : 'text-gray-500 dark:text-bambu-text-dim' }}">
                                {{ $label }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

</div>
