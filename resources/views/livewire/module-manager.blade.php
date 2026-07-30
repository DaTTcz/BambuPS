<div class="p-6 space-y-6">

    @foreach($this->modules as $module)
        <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden">

            {{-- Header modulu --}}
            <div class="p-5 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center
                        {{ $module->enabled ? 'bg-green-100 dark:bg-green-900/30' : 'bg-gray-100 dark:bg-bambu-dark-3' }}">
                        <span class="text-xl">{{ $module->enabled ? '✅' : '⚪' }}</span>
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <p class="font-semibold text-gray-800 dark:text-bambu-text">{{ $module->label }}</p>
                            @if($module->name === 'mqtt_connector')
                                <span class="px-2 py-0.5 text-xs rounded-full font-medium
                                    {{ $this->mqttStatus === 'running' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-bambu-green' : 'bg-gray-100 dark:bg-bambu-dark-3 text-gray-500 dark:text-bambu-text-dim' }}">
                                    {{ $this->mqttStatus === 'running' ? '● Běží' : '○ Zastaveno' }}
                                </span>
                            @endif
                            @if($module->name === 'go2rtc')
                                <span class="px-2 py-0.5 text-xs rounded-full font-medium
                                    {{ $this->go2rtcStatus === 'running' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-bambu-green' : 'bg-gray-100 dark:bg-bambu-dark-3 text-gray-500 dark:text-bambu-text-dim' }}">
                                    {{ $this->go2rtcStatus === 'running' ? '● Běží' : '○ Zastaveno' }}
                                </span>
                            @endif
                            @if($module->name === 'slicer_connector')
                                <span class="px-2 py-0.5 text-xs rounded-full font-medium
                                    {{ $module->enabled ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-bambu-green' : 'bg-gray-100 dark:bg-bambu-dark-3 text-gray-500 dark:text-bambu-text-dim' }}">
                                    {{ $module->enabled ? '● Aktivní' : '○ Neaktivní' }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 dark:text-bambu-text-dim font-mono">{{ $module->name }}</p>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <button wire:click="startEdit({{ $module->id }})"
                        class="px-3 py-1.5 text-sm bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 dark:hover:bg-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg">
                        ⚙️ Konfigurace
                    </button>
                    <button wire:click="toggle({{ $module->id }})"
                        class="px-3 py-1.5 text-sm rounded-lg font-medium
                            {{ $module->enabled
                                ? 'bg-red-100 dark:bg-red-900/30 hover:bg-red-200 text-red-700 dark:text-red-400'
                                : 'bg-green-100 dark:bg-green-900/30 hover:bg-green-200 text-green-700 dark:text-bambu-green' }}">
                        {{ $module->enabled ? 'Deaktivovat' : 'Aktivovat' }}
                    </button>
                </div>
            </div>

            {{-- Konfigurace panel --}}
            @if($editingId === $module->id)
                <div class="px-5 pb-5 border-t border-gray-100 dark:border-bambu-dark-4 pt-4">

                    {{-- MQTT konfigurace --}}
                    @if($module->name === 'mqtt_connector')
                        <h4 class="text-sm font-semibold text-gray-600 dark:text-bambu-text-dim mb-3">Konfigurace MQTT</h4>
                        <div class="space-y-3">
                            @foreach($editingConfig as $key => $value)
                                <div class="flex items-center space-x-3">
                                    <label class="text-sm text-gray-500 dark:text-bambu-text-dim w-40 shrink-0 font-mono">{{ $key }}</label>
                                    <input type="{{ is_numeric($value) ? 'number' : 'text' }}"
                                        wire:model="editingConfig.{{ $key }}"
                                        class="flex-1 border border-gray-300 dark:border-bambu-dark-4 rounded-lg px-3 py-1.5 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
                                </div>
                            @endforeach
                        </div>
                        <div class="flex justify-end space-x-3 mt-4">
                            <button wire:click="$set('editingId', null)" class="px-4 py-2 text-sm text-gray-600 dark:text-bambu-text-dim">Zrušit</button>
                            <button wire:click="saveConfig" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm">Uložit</button>
                        </div>
                    @endif

                    {{-- go2rtc konfigurace --}}
                    @if($module->name === 'go2rtc')
                        <h4 class="text-sm font-semibold text-gray-600 dark:text-bambu-text-dim mb-3">Konfigurace go2rtc</h4>
                        <div class="space-y-3 mb-4">
                            @foreach($editingConfig as $key => $value)
                                <div class="flex items-center space-x-3">
                                    <label class="text-sm text-gray-500 dark:text-bambu-text-dim w-40 shrink-0 font-mono">{{ $key }}</label>
                                    <input type="number"
                                        wire:model="editingConfig.{{ $key }}"
                                        class="flex-1 border border-gray-300 dark:border-bambu-dark-4 rounded-lg px-3 py-1.5 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
                                </div>
                            @endforeach
                        </div>

                        <div class="bg-gray-50 dark:bg-bambu-dark-3 rounded-lg p-4 mb-4">
                            <h5 class="text-xs font-semibold text-gray-600 dark:text-bambu-text-dim mb-2">Aktivní streamy</h5>
                            @php $printers = \App\Models\Printer::where('enabled', true)->get(); @endphp
                            @if($printers->count() > 0)
                                @foreach($printers as $printer)
                                    <div class="flex items-center justify-between py-1">
                                        <span class="text-xs font-mono text-gray-600 dark:text-bambu-text-dim">printer_{{ $printer->id }}</span>
                                        <span class="text-xs text-gray-500 dark:text-bambu-text-dim">{{ $printer->name }} ({{ $printer->ip_address }})</span>
                                    </div>
                                @endforeach
                            @else
                                <p class="text-xs text-gray-400 dark:text-bambu-text-dim">Žádné aktivní tiskárny</p>
                            @endif
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button wire:click="$set('editingId', null)" class="px-4 py-2 text-sm text-gray-600 dark:text-bambu-text-dim">Zrušit</button>
                            <button wire:click="saveConfig" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm">Uložit a restartovat</button>
                        </div>
                    @endif

                    {{-- Slicer konfigurace --}}
                    @if($module->name === 'slicer_connector')
                        @if(!$module->enabled)
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 mb-4 text-sm text-yellow-800 dark:text-yellow-400">
                                ⚠️ Aktivuj modul pro příjem souborů ze sliceru.
                            </div>
                        @endif

                        <div class="bg-green-50 dark:bg-bambu-dark-3 border border-green-200 dark:border-bambu-dark-4 rounded-lg p-4 mb-5">
                            <p class="text-sm font-semibold text-green-800 dark:text-bambu-green mb-2">Nastavení v OrcaSliceru / Bambu Studiu</p>
                            <div class="space-y-1 text-xs text-green-700 dark:text-bambu-text-dim">
                                <p><span class="font-mono font-semibold">Host Type:</span> Octo/Klipper</p>
                                <p><span class="font-mono font-semibold">Printer Agent:</span> Orca</p>
                                <p><span class="font-mono font-semibold">Hostname:</span>
                                    <span class="font-mono bg-white dark:bg-bambu-dark-4 px-1 rounded">http://{{ request()->getHost() }}:8080</span>
                                </p>
                                <p><span class="font-mono font-semibold">API Key:</span> zkopíruj token níže</p>
                            </div>
                        </div>

                        <h4 class="text-sm font-semibold text-gray-700 dark:text-bambu-text mb-3">API tokeny pro slicery</h4>

                        @if($newTokenValue)
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-300 dark:border-green-800 rounded-lg p-4 mb-4">
                                <p class="text-sm font-semibold text-green-800 dark:text-green-400 mb-2">⚠️ Zkopíruj token – zobrazí se jen jednou!</p>
                                <div class="flex items-center space-x-2">
                                    <code class="flex-1 bg-white dark:bg-bambu-dark-3 border border-green-200 dark:border-bambu-dark-4 rounded px-3 py-2 text-xs font-mono break-all dark:text-bambu-text">{{ $newTokenValue }}</code>
                                    <button onclick="
                                        navigator.clipboard.writeText('{{ $newTokenValue }}');
                                        this.textContent = 'Zkopírováno!';
                                        setTimeout(() => this.textContent = 'Kopírovat', 2000);
                                    " class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs shrink-0">
                                        Kopírovat
                                    </button>
                                </div>
                                <button wire:click="$set('newTokenValue', null)" class="mt-2 text-xs text-green-700 dark:text-green-400 hover:underline">Zavřít</button>
                            </div>
                        @endif

                        <div class="space-y-1 mb-4">
                            <div class="flex items-center space-x-3">
                                <input wire:model="newTokenName" type="text" placeholder="Název tokenu (např. OrcaSlicer-PC)"
                                    class="flex-1 border border-gray-300 dark:border-bambu-dark-4 rounded-lg px-3 py-2 text-sm dark:bg-bambu-dark-3 dark:text-bambu-text">
                                <button wire:click="createSlicerToken"
                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm shrink-0">
                                    + Vytvořit token
                                </button>
                            </div>
                            @error('newTokenName')
                                <p class="text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        @if($this->slicerTokens->count() > 0)
                            <div class="space-y-2 mb-4">
                                @foreach($this->slicerTokens as $token)
                                    <div class="flex items-center justify-between bg-gray-50 dark:bg-bambu-dark-3 rounded-lg px-4 py-2">
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 dark:text-bambu-text">{{ str_replace('Slicer:', '', $token->name) }}</p>
                                            <p class="text-xs text-gray-400 dark:text-bambu-text-dim">Vytvořen: {{ $token->created_at->format('d.m.Y H:i') }}</p>
                                        </div>
                                        <button wire:click="deleteToken({{ $token->id }})"
                                            wire:confirm="Opravdu smazat token?"
                                            class="px-3 py-1 text-xs bg-red-100 dark:bg-red-900/30 hover:bg-red-200 text-red-700 dark:text-red-400 rounded-lg">
                                            Smazat
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-400 dark:text-bambu-text-dim text-center py-3 mb-4">Žádné tokeny. Vytvoř první token výše.</p>
                        @endif

                        <div class="flex justify-end">
                            <button wire:click="$set('editingId', null)" class="px-4 py-2 text-sm text-gray-600 dark:text-bambu-text-dim hover:text-gray-800">Zavřít</button>
                        </div>
                    @endif

                </div>
            @endif

        </div>
    @endforeach

</div>
