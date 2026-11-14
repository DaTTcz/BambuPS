<div class="space-y-6">
    <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-bambu-dark-4">
            <h3 class="font-semibold text-gray-800 dark:text-bambu-text">🕒 Časová zóna</h3>
            <p class="text-xs text-gray-500 dark:text-bambu-text-dim mt-0.5">
                Podle ní se zobrazují časy v appce a v notifikacích. Letní a zimní čas se přepíná sám.
                Uložená data se nemění, takže změna platí i pro starou historii.
            </p>
        </div>
        <div class="p-5 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-bambu-text mb-1">Zóna</label>
                    <select wire:model.live="timezone"
                        class="w-full border border-gray-300 dark:border-bambu-dark-4 dark:bg-bambu-dark-3 dark:text-bambu-text rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                        @foreach($this->timezoneGroups as $region => $zones)
                            <optgroup label="{{ $region }}">
                                @foreach($zones as $tz)
                                    <option value="{{ $tz }}">{{ str_replace('_', ' ', $tz) }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('timezone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="text-sm text-gray-600 dark:text-bambu-text-dim">
                    Aktuální čas v této zóně:<br>
                    <span class="font-mono font-semibold text-gray-800 dark:text-bambu-text">{{ $this->preview }}</span>
                </div>
            </div>
            <div class="flex justify-end">
                <button wire:click="save"
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium">Uložit</button>
            </div>
        </div>
    </div>
</div>
