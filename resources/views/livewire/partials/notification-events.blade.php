{{--
    Seznam událostí jednoho kanálu: přepínač (uloží se hned) + klik na název otevře editor šablony.
    Parametry: $channel ('email'|'telegram'|'mqtt'), $custom ([událost => true] pro upravené šablony)
--}}
<div>
    <p class="text-sm font-medium text-gray-700 mb-2">
        Odesílat při:
        <span class="font-normal text-xs text-gray-400">– kliknutím na událost upravíš text zprávy</span>
    </p>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach(\App\Services\NotificationTemplates::EVENTS as $evKey => $ev)
            <div wire:key="ev-{{ $channel }}-{{ $evKey }}"
                class="flex items-center bg-gray-50 rounded-lg pl-3 pr-1 py-1 group hover:bg-gray-100 transition-colors">
                <input wire:model.live="{{ $channel }}{{ $ev['suffix'] }}" type="checkbox"
                    class="rounded text-blue-600 shrink-0" title="Zapnout / vypnout">
                <button type="button" wire:click="openTemplate('{{ $channel }}', '{{ $evKey }}')"
                    class="flex-1 min-w-0 flex items-center gap-2 text-left px-2 py-1 rounded-md">
                    <span class="text-sm text-gray-600 truncate">{{ $ev['icon'] }} {{ $ev['label'] }}</span>
                    @if(!empty($custom[$evKey]))
                        <span class="px-1.5 py-0.5 text-xs leading-none rounded-full bg-blue-100 text-blue-700 shrink-0">upraveno</span>
                    @endif
                    <span class="ml-auto text-xs text-gray-300 group-hover:text-blue-600 shrink-0">✏️</span>
                </button>
            </div>
        @endforeach
    </div>
</div>
