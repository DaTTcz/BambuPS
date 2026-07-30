<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-3">
            <a href="{{ route('printers') }}" class="text-gray-400 hover:text-gray-600">← Tiskárny</a>
            <span class="text-gray-300">/</span>
            <h2 class="font-semibold text-xl text-gray-800">{{ $printer->name }}</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @livewire('printer-detail', ['printer' => $printer])
        </div>
    </div>
</x-app-layout>
