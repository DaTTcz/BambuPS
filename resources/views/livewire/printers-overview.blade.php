<div class="space-y-4" wire:poll.5000ms>
    @if($printers->count() === 0)
        <div class="bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4 rounded-xl p-12 text-center text-gray-400 dark:text-bambu-text-dim">
            <p class="text-4xl mb-3">🖨️</p>
            <p class="font-medium">Žádné tiskárny</p>
            <p class="text-sm mt-1">Přidej tiskárnu v <a href="{{ route('printers.manage') }}" class="hover:underline">Správě tiskáren</a></p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($printers as $printer)
                <x-printer-card :printer="$printer" />
            @endforeach
        </div>
    @endif
</div>
