<div class="space-y-6">

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
            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Odesílat při:</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label class="flex items-center space-x-2 bg-gray-50 rounded-lg px-3 py-2">
                        <input wire:model="emailOnDone" type="checkbox" class="rounded text-blue-600">
                        <span class="text-sm text-gray-600">✅ Dokončení tisku</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-50 rounded-lg px-3 py-2">
                        <input wire:model="emailOnFailed" type="checkbox" class="rounded text-blue-600">
                        <span class="text-sm text-gray-600">❌ Selhání tisku</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-50 rounded-lg px-3 py-2">
                        <input wire:model="emailOnHms" type="checkbox" class="rounded text-blue-600">
                        <span class="text-sm text-gray-600">⚠️ HMS varování</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-50 rounded-lg px-3 py-2">
                        <input wire:model="emailOnFilament" type="checkbox" class="rounded text-blue-600">
                        <span class="text-sm text-gray-600">🧵 Konec filamentu</span>
                    </label>
                </div>
            </div>
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
            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Odesílat při:</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label class="flex items-center space-x-2 bg-gray-50 rounded-lg px-3 py-2">
                        <input wire:model="telegramOnDone" type="checkbox" class="rounded text-blue-600">
                        <span class="text-sm text-gray-600">✅ Dokončení tisku</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-50 rounded-lg px-3 py-2">
                        <input wire:model="telegramOnFailed" type="checkbox" class="rounded text-blue-600">
                        <span class="text-sm text-gray-600">❌ Selhání tisku</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-50 rounded-lg px-3 py-2">
                        <input wire:model="telegramOnHms" type="checkbox" class="rounded text-blue-600">
                        <span class="text-sm text-gray-600">⚠️ HMS varování</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-50 rounded-lg px-3 py-2">
                        <input wire:model="telegramOnFilament" type="checkbox" class="rounded text-blue-600">
                        <span class="text-sm text-gray-600">🧵 Konec filamentu</span>
                    </label>
                </div>
            </div>
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
            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Odesílat při:</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label class="flex items-center space-x-2 bg-gray-50 rounded-lg px-3 py-2">
                        <input wire:model="mqttOnDone" type="checkbox" class="rounded text-blue-600">
                        <span class="text-sm text-gray-600">✅ Dokončení tisku</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-50 rounded-lg px-3 py-2">
                        <input wire:model="mqttOnFailed" type="checkbox" class="rounded text-blue-600">
                        <span class="text-sm text-gray-600">❌ Selhání tisku</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-50 rounded-lg px-3 py-2">
                        <input wire:model="mqttOnHms" type="checkbox" class="rounded text-blue-600">
                        <span class="text-sm text-gray-600">⚠️ HMS varování</span>
                    </label>
                    <label class="flex items-center space-x-2 bg-gray-50 rounded-lg px-3 py-2">
                        <input wire:model="mqttOnFilament" type="checkbox" class="rounded text-blue-600">
                        <span class="text-sm text-gray-600">🧵 Konec filamentu</span>
                    </label>
                </div>
            </div>
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

</div>
