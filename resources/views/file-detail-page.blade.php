<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-3">
            <a href="{{ route('files') }}" class="text-gray-400 hover:text-gray-600">← Soubory</a>
            <span class="text-gray-300">/</span>
            <h2 class="font-semibold text-xl text-gray-800 truncate">{{ $file->original_name }}</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @livewire('file-detail-page', ['file' => $file])
        </div>
    </div>
</x-app-layout>
