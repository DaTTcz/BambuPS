<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-bambu-text leading-tight">
            🔔 Notifikace
        </h2>
    </x-slot>
    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @livewire('notification-settings')
        </div>
    </div>
</x-app-layout>
