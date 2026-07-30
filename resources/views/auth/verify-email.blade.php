<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>
        <div class="mb-4 text-sm text-gray-600">
            {{ __('Před pokračováním ověřte svou emailovou adresu kliknutím na odkaz, který jsme vám zaslali. Pokud jste email neobdrželi, zašleme vám nový.') }}
        </div>
        @if (session('status') == 'verification-link-sent')
            <div class="mb-4 font-medium text-sm text-green-600">
                {{ __('Nový ověřovací odkaz byl odeslán na vaši emailovou adresu.') }}
            </div>
        @endif
        <div class="mt-4 flex items-center justify-between">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <div>
                    <x-button type="submit">{{ __('Znovu odeslat ověřovací email') }}</x-button>
                </div>
            </form>
            <div>
                <a href="{{ route('profile.show') }}"
                    class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    {{ __('Upravit profil') }}
                </a>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 ms-2">
                        {{ __('Odhlásit se') }}
                    </button>
                </form>
            </div>
        </div>
    </x-authentication-card>
</x-guest-layout>
