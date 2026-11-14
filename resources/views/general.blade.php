<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-bambu-text leading-tight">
            ⚙️ Obecné
        </h2>
    </x-slot>
    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @livewire('general-settings')
        </div>
    </div>
</x-app-layout>
