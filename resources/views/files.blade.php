<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-bambu-text leading-tight">
            📁 Soubory
        </h2>
    </x-slot>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-bambu-dark-2 overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-bambu-dark-4">
                @livewire('file-manager')
            </div>
        </div>
    </div>
    @livewire('file-detail')
</x-app-layout>
