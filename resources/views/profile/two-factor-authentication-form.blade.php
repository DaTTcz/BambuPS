<x-action-section>
    <x-slot name="title">
        {{ __('Dvoufaktorové ověření') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Přidejte dvoufaktorové ověření pro vyšší bezpečnost účtu.') }}
    </x-slot>

    <x-slot name="content">
        <h3 class="text-lg font-medium text-gray-900">
            @if ($this->enabled)
                @if ($showingPotvrditation)
                    {{ __('Dokončete aktivaci dvoufaktorového ověření.') }}
                @else
                    {{ __('Dvoufaktorové ověření je aktivní.') }}
                @endif
            @else
                {{ __('Dvoufaktorové ověření není aktivní.') }}
            @endif
        </h3>

        <div class="mt-3 max-w-xl text-sm text-gray-600">
            <p>
                {{ __('Po aktivaci budete při přihlášení vyzváni k zadání kódu z aplikace Google Authenticator.') }}
            </p>
        </div>

        @if ($this->enabled)
            @if ($showingQrCode)
                <div class="mt-4 max-w-xl text-sm text-gray-600">
                    <p class="font-semibold">
                        @if ($showingPotvrditation)
                            {{ __('Naskenujte QR kód v aplikaci Google Authenticator nebo zadejte klíč ručně a poté zadejte vygenerovaný kód.') }}
                        @else
                            {{ __('Dvoufaktorové ověření je aktivní. Naskenujte QR kód v aplikaci Google Authenticator nebo zadejte klíč ručně.') }}
                        @endif
                    </p>
                </div>

                <div class="mt-4 p-2 inline-block bg-white">
                    {!! $this->user->twoFactorQrCodeSvg() !!}
                </div>

                <div class="mt-4 max-w-xl text-sm text-gray-600">
                    <p class="font-semibold">
                        {{ __('Klíč') }}: {{ decrypt($this->user->two_factor_secret) }}
                    </p>
                </div>

                @if ($showingPotvrditation)
                    <div class="mt-4">
                        <x-label for="code" value="{{ __('Kód') }}" />
                        <x-input id="code" type="text" name="code" class="block mt-1 w-1/2" inputmode="numeric" autofocus autocomplete="one-time-code"
                            wire:model="code"
                            wire:keydown.enter="confirmTwoFactorAuthentication" />
                        <x-input-error for="code" class="mt-2" />
                    </div>
                @endif
            @endif

            @if ($showingRecoveryCodes)
                <div class="mt-4 max-w-xl text-sm text-gray-600">
                    <p class="font-semibold">
                        {{ __('Uložte tyto záložní kódy na bezpečné místo. Použijete je pro přístup k účtu pokud ztratíte přístup k aplikaci.') }}
                    </p>
                </div>

                <div class="grid gap-1 max-w-xl mt-4 px-4 py-4 font-mono text-sm bg-gray-100 rounded-lg">
                    @foreach (json_decode(decrypt($this->user->two_factor_recovery_codes), true) as $code)
                        <div>{{ $code }}</div>
                    @endforeach
                </div>
            @endif
        @endif

        <div class="mt-5">
            @if (! $this->enabled)
                <x-confirms-password wire:then="enableTwoFactorAuthentication">
                    <x-button type="button" wire:loading.attr="disabled">
                        {{ __('Aktivovat') }}
                    </x-button>
                </x-confirms-password>
            @else
                @if ($showingRecoveryCodes)
                    <x-confirms-password wire:then="regenerateRecoveryCodes">
                        <x-secondary-button class="me-3">
                            {{ __('Obnovit záložní kódy') }}
                        </x-secondary-button>
                    </x-confirms-password>
                @elseif ($showingPotvrditation)
                    <x-confirms-password wire:then="confirmTwoFactorAuthentication">
                        <x-button type="button" class="me-3" wire:loading.attr="disabled">
                            {{ __('Potvrdit') }}
                        </x-button>
                    </x-confirms-password>
                @else
                    <x-confirms-password wire:then="showRecoveryCodes">
                        <x-secondary-button class="me-3">
                            {{ __('Zobrazit záložní kódy') }}
                        </x-secondary-button>
                    </x-confirms-password>
                @endif

                @if ($showingPotvrditation)
                    <x-confirms-password wire:then="disableTwoFactorAuthentication">
                        <x-secondary-button wire:loading.attr="disabled">
                            {{ __('Zrušit') }}
                        </x-secondary-button>
                    </x-confirms-password>
                @else
                    <x-confirms-password wire:then="disableTwoFactorAuthentication">
                        <x-danger-button wire:loading.attr="disabled">
                            {{ __('Deaktivovat') }}
                        </x-danger-button>
                    </x-confirms-password>
                @endif
            @endif
        </div>
    </x-slot>
</x-action-section>
