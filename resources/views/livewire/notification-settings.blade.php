<div class="space-y-6">
    @php $customTemplates = $this->customTemplates; @endphp

    {{-- SMTP nastavení --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center space-x-3">
            <span class="text-2xl">⚙️</span>
            <div>
                <h3 class="font-semibold text-gray-800">SMTP – Odesílací server</h3>
                <p class="text-xs text-gray-400">Nastavení pro odesílání emailových notifikací</p>
            </div>
        </div>
        <div class="p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
                    <input wire:model="smtpHost" type="text" placeholder="smtp.gmail.com"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                    <input wire:model="smtpPort" type="text" placeholder="587"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Uživatelské jméno</label>
                    <input wire:model="smtpUsername" type="text" placeholder="vas@gmail.com"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Heslo / App heslo</label>
                    <input wire:model="smtpPassword" type="password" placeholder="••••••••"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Šifrování</label>
                    <select wire:model="smtpEncryption"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="tls">TLS (doporučeno)</option>
                        <option value="ssl">SSL</option>
                        <option value="">Žádné</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jméno odesílatele</label>
                    <input wire:model="smtpFromName" type="text" placeholder="BambuPS"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email odesílatele</label>
                <input wire:model="smtpFromEmail" type="email" placeholder="bambups@vas-server.cz"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex justify-end pt-2">
                <button wire:click="saveSmtp"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                    Uložit SMTP
                </button>
            </div>
        </div>
    </div>

    {{-- Email --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-2xl">📧</span>
                <div>
                    <h3 class="font-semibold text-gray-800">Email</h3>
                    <p class="text-xs text-gray-400">Notifikace na emailovou adresu</p>
                </div>
            </div>
            <label class="flex items-center space-x-2 cursor-pointer">
                <input wire:model="emailEnabled" type="checkbox" class="rounded text-blue-600">
                <span class="text-sm text-gray-600">{{ $emailEnabled ? 'Aktivní' : 'Neaktivní' }}</span>
            </label>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Emailová adresa</label>
                <input wire:model="emailAddress" type="email" placeholder="vas@email.cz"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            @include('livewire.partials.notification-events', ['channel' => 'email', 'custom' => $customTemplates['email'] ?? []])
            <div class="flex space-x-3 pt-2">
                <button wire:click="testEmail"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm">
                    📨 Testovat
                </button>
                <button wire:click="saveEmail"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                    Uložit
                </button>
            </div>
        </div>
    </div>

    {{-- Telegram --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-2xl">✈️</span>
                <div>
                    <h3 class="font-semibold text-gray-800">Telegram</h3>
                    <p class="text-xs text-gray-400">Zprávy přes Telegram bota</p>
                </div>
            </div>
            <label class="flex items-center space-x-2 cursor-pointer">
                <input wire:model="telegramEnabled" type="checkbox" class="rounded text-blue-600">
                <span class="text-sm text-gray-600">{{ $telegramEnabled ? 'Aktivní' : 'Neaktivní' }}</span>
            </label>
        </div>
        <div class="p-6 space-y-4">
            <div class="bg-blue-50 rounded-lg p-3 text-xs text-blue-700">
                <p class="font-medium mb-1">Jak nastavit Telegram bota:</p>
                <ol class="list-decimal list-inside space-y-1">
                    <li>Otevři @BotFather v Telegramu a vytvoř nového bota</li>
                    <li>Zkopíruj Bot Token</li>
                    <li>Pošli botu zprávu a získej Chat ID z @userinfobot</li>
                </ol>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bot Token</label>
                    <input wire:model="telegramBotToken" type="text" placeholder="123456:ABC-DEF..."
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chat ID</label>
                    <input wire:model="telegramChatId" type="text" placeholder="-100123456789"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            @include('livewire.partials.notification-events', ['channel' => 'telegram', 'custom' => $customTemplates['telegram'] ?? []])
            <div class="flex space-x-3 pt-2">
                <button wire:click="testTelegram"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm">
                    📨 Testovat
                </button>
                <button wire:click="saveTelegram"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                    Uložit
                </button>
            </div>
        </div>
    </div>

    {{-- MQTT --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-2xl">🔌</span>
                <div>
                    <h3 class="font-semibold text-gray-800">MQTT / Home Assistant</h3>
                    <p class="text-xs text-gray-400">Publikování událostí na MQTT broker</p>
                </div>
            </div>
            <label class="flex items-center space-x-2 cursor-pointer">
                <input wire:model="mqttEnabled" type="checkbox" class="rounded text-blue-600">
                <span class="text-sm text-gray-600">{{ $mqttEnabled ? 'Aktivní' : 'Neaktivní' }}</span>
            </label>
        </div>
        <div class="p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Host</label>
                    <input wire:model="mqttHost" type="text" placeholder="192.168.1.x nebo homeassistant.local"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                    <input wire:model="mqttPort" type="text" placeholder="1883"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Uživatelské jméno</label>
                    <input wire:model="mqttUsername" type="text" placeholder="volitelné"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Heslo</label>
                    <input wire:model="mqttPassword" type="password" placeholder="volitelné"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Topic</label>
                <input wire:model="mqttTopic" type="text" placeholder="bambups/events"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            @include('livewire.partials.notification-events', ['channel' => 'mqtt', 'custom' => $customTemplates['mqtt'] ?? []])
            <div class="flex space-x-3 pt-2">
                <button wire:click="testMqtt"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm">
                    📨 Testovat
                </button>
                <button wire:click="saveMqtt"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                    Uložit
                </button>
            </div>
        </div>
    </div>

    {{-- ================= Editor šablony ================= --}}
    @if($editEvent)
        @php
            $evDef   = \App\Services\NotificationTemplates::event($editEvent);
            $chNames = ['email' => '📧 Email', 'telegram' => '✈️ Telegram', 'mqtt' => '🔌 MQTT'];
            $preview = $this->templatePreview;
        @endphp
        <div class="fixed inset-0 bg-black/50 flex items-start sm:items-center justify-center z-50 overflow-y-auto p-4"
            wire:keydown.escape.window="closeTemplate">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl border border-gray-100"
                x-data="{
                    target: 'body',
                    insert(name) {
                        const t = this.$refs[this.target] ?? this.$refs.body;
                        const v = '{' + name + '}';
                        const s = t.selectionStart ?? t.value.length, e = t.selectionEnd ?? t.value.length;
                        t.value = t.value.slice(0, s) + v + t.value.slice(e);
                        t.focus();
                        t.selectionStart = t.selectionEnd = s + v.length;
                        t.dispatchEvent(new Event('input'));
                    }
                }">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-800">{{ $evDef['icon'] }} {{ $evDef['label'] }}</h3>
                        <p class="text-xs text-gray-400">Šablona pro kanál {{ $chNames[$editChannel] ?? $editChannel }}</p>
                    </div>
                    <button wire:click="closeTemplate" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
                </div>

                <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Editor --}}
                    <div class="space-y-4">
                        @if($editChannel === 'email')
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Předmět e-mailu</label>
                                <input x-ref="subject" @focus="target = 'subject'" wire:model.live.debounce.300ms="editSubject" type="text"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('editSubject') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Text zprávy</label>
                            <textarea x-ref="body" @focus="target = 'body'" wire:model.live.debounce.300ms="editBody" rows="9"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            @error('editBody') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-gray-400 mt-1">
                                Řádek, kde jsou všechny proměnné prázdné, se vynechá.
                                @if($editChannel === 'telegram')
                                    Formátování: &lt;b&gt;tučně&lt;/b&gt;, &lt;i&gt;kurzíva&lt;/i&gt;, &lt;code&gt;kód&lt;/code&gt;.
                                @else
                                    HTML značky (&lt;b&gt; apod.) se v tomhle kanálu odstraní.
                                @endif
                            </p>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-700 mb-1">Proměnné <span class="font-normal text-xs text-gray-400">– klikni pro vložení</span></p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($evDef['vars'] as $var)
                                    <button type="button" @click="insert('{{ $var }}')"
                                        title="{{ \App\Services\NotificationTemplates::VAR_LABELS[$var] ?? '' }}"
                                        class="px-2 py-0.5 text-xs font-mono rounded-md bg-blue-50 text-blue-700 hover:bg-blue-100">{{ '{' . $var . '}' }}</button>
                                @endforeach
                            </div>
                        </div>

                        @if($editChannel !== 'mqtt')
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input wire:model.live="editImage" type="checkbox" class="rounded text-blue-600">
                                <span class="text-sm text-gray-700">
                                    Přiložit obrázek
                                    <span class="text-xs text-gray-400">
                                        @if($editEvent === 'print_start') (náhled desky ze souboru)
                                        @elseif($editEvent === 'print_done') (snímek z kamery, jinak náhled desky)
                                        @elseif($editEvent === 'print_failed') (snímek z kamery)
                                        @else (u téhle události obvykle žádný není)
                                        @endif
                                    </span>
                                </span>
                            </label>
                        @else
                            <p class="text-xs text-gray-400">MQTT posílá JSON: <code>event</code>, <code>printer</code>, <code>message</code> (tenhle text), <code>vars</code> (všechny proměnné) a <code>time</code>.</p>
                        @endif
                    </div>

                    {{-- Náhled --}}
                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-1">Náhled <span class="font-normal text-xs text-gray-400">– s ukázkovými daty</span></p>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-2">
                            @if($preview && $preview['subject'] !== null)
                                <p class="text-xs text-gray-500">Předmět: <span class="font-medium text-gray-800">{{ $preview['subject'] }}</span></p>
                                <hr class="border-gray-200">
                            @endif
                            @if($editChannel !== 'mqtt' && $editImage)
                                <div class="h-24 w-24 rounded-lg bg-gray-200 flex items-center justify-center text-xs text-gray-400">🖼️ obrázek</div>
                            @endif
                            <div class="text-sm text-gray-800 break-words">{!! $preview['body'] ?? '' !!}</div>
                        </div>
                        <dl class="mt-3 text-xs text-gray-500 space-y-0.5">
                            @foreach($evDef['vars'] as $var)
                                <div class="flex gap-2"><dt class="font-mono text-gray-600 w-32 shrink-0">{{ '{' . $var . '}' }}</dt><dd>{{ \App\Services\NotificationTemplates::VAR_LABELS[$var] ?? '' }}</dd></div>
                            @endforeach
                        </dl>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex flex-wrap items-center gap-3">
                    <button wire:click="resetTemplate" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800">↺ Obnovit výchozí</button>
                    <button wire:click="testTemplate" wire:loading.attr="disabled" wire:target="testTemplate"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm disabled:opacity-50">
                        <span wire:loading.remove wire:target="testTemplate">📨 Odeslat zkušebně</span>
                        <span wire:loading wire:target="testTemplate">Odesílám...</span>
                    </button>
                    <div class="flex-1"></div>
                    <button wire:click="closeTemplate" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm">Zrušit</button>
                    <button wire:click="saveTemplate" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">Uložit</button>
                </div>
            </div>
        </div>
    @endif

</div>
