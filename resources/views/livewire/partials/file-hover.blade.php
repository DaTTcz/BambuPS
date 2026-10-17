{{--
    Hover panel s detaily souboru – sdílený pro kartové i řádkové zobrazení.
    Parametry: $file (App\Models\File), $withImage (bool – zvětšený náhled nahoře).
    Musí být uvnitř prvku s x-data="@include('livewire.partials.file-hover-xdata')".
    position: fixed => panel neořízne overflow-hidden karty ani tabulky.
--}}
@php
    // Detaily pro hover kartu – skládáme jen to, co parser skutečně našel
    $m      = $file->metadata ?? [];
    $plates = $m['plates'] ?? [];

    // Filamenty ze všech desek, sloučené podle typu + barvy
    $hoverFilaments = [];
    foreach ($plates as $pl) {
        foreach (($pl['filaments'] ?? []) as $f) {
            $key = strtoupper(($f['type'] ?? '?') . '|' . ($f['color'] ?? ''));
            if (!isset($hoverFilaments[$key])) {
                $hoverFilaments[$key] = ['type' => $f['type'] ?? '?', 'color' => $f['color'] ?? '#888888', 'g' => 0];
            }
            $hoverFilaments[$key]['g'] += (float) ($f['used_g'] ?? 0);
        }
    }

    $nozzle  = $plates[0]['nozzle_diameter'] ?? null;
    $layers  = $m['total_layer_num'] ?? ($plates[0]['total_layer_num'] ?? null);
    $bedType = $m['bed_type_canonical'] ?? ($m['bed_type'] ?? null);

    $hoverRows = array_filter([
        'Tiskárna'     => $m['printer_model'] ?? null,
        'Čas tisku'    => $m['print_time'] ?? null,
        'Filament'     => !empty($m['filament_used_g'])
                            ? $m['filament_used_g'] . ' g' . (!empty($m['filament_used_m']) ? ' · ' . $m['filament_used_m'] . ' m' : '')
                            : null,
        'Tryska'       => $nozzle ? $nozzle . ' mm' : null,
        'Výška vrstvy' => !empty($m['layer_height'])
                            ? $m['layer_height'] . ' mm' . (!empty($m['initial_layer_height']) ? ' (1. vrstva ' . $m['initial_layer_height'] . ' mm)' : '')
                            : null,
        'Vrstev'       => $layers ?: null,
        'Teplota trysky' => !empty($m['nozzle_temp']) ? $m['nozzle_temp'] . ' °C' : null,
        'Podložka'     => $bedType
                            ? $bedType . (!empty($m['bed_temp']) ? ' · ' . $m['bed_temp'] . ' °C' : '')
                            : null,
        'Výplň'        => !empty($m['infill_density'])
                            ? $m['infill_density'] . (!empty($m['infill_pattern']) ? ' · ' . $m['infill_pattern'] : '')
                            : null,
        'Stěny'        => !empty($m['wall_loops']) ? $m['wall_loops'] . '×' : null,
        'Podpěry'      => array_key_exists('support_used', $m)
                            ? ($m['support_used'] ? ($m['support_type'] ?? 'ano') : 'ne')
                            : null,
        'Vázový režim' => !empty($m['spiral_mode']) ? 'ano' : null,
        'Desky'        => (($m['plate_count'] ?? 0) > 1) ? $m['plate_count'] : null,
        'Profil'       => $m['print_profile'] ?? null,
        'Velikost'     => $file->size_formatted,
        'Nahráno'      => $file->created_at?->format('d.m.Y H:i'),
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<div x-ref="pop" x-show="open" x-cloak
    x-transition.opacity.duration.150ms
    :style="`left:${x}px;top:${y}px`"
    class="fixed z-50 w-72 max-h-[80vh] overflow-hidden rounded-xl shadow-xl border border-gray-200 dark:border-bambu-dark-4 bg-white dark:bg-bambu-dark-2 pointer-events-none cursor-default">
    @if(!empty($withImage))
        <div class="h-48 bg-gray-50 dark:bg-bambu-dark-3 border-b border-gray-100 dark:border-bambu-dark-4 flex items-center justify-center">
            @if($file->list_thumbnail_url)
                <img src="{{ $file->list_thumbnail_url }}" class="w-full h-full object-contain p-2" alt="náhled">
            @elseif(str_ends_with(strtolower($file->original_name), '.gcode'))
                <img src="/images/gcode-placeholder.png" class="w-full h-full object-contain p-4" alt="gcode">
            @else
                <span class="text-5xl">🗂️</span>
            @endif
        </div>
    @endif
    <div class="px-3 py-2.5 border-b border-gray-100 dark:border-bambu-dark-4">
        <p class="text-sm font-semibold text-gray-800 dark:text-bambu-text break-all leading-snug">{{ $file->original_name }}</p>
        @if(!empty($m['has_gcode']))
            <span class="inline-block mt-1 px-1.5 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-bambu-green text-xs rounded-full">G-code</span>
        @endif
    </div>

    @if(count($hoverFilaments))
        <div class="px-3 py-2 border-b border-gray-100 dark:border-bambu-dark-4 space-y-1">
            @foreach($hoverFilaments as $f)
                <div class="flex items-center justify-between text-xs">
                    <span class="flex items-center gap-1.5 min-w-0">
                        <span class="w-3 h-3 rounded-full border border-black/10 dark:border-white/20 shrink-0" style="background: {{ $f['color'] }}"></span>
                        <span class="text-gray-700 dark:text-bambu-text truncate">{{ $f['type'] }}</span>
                    </span>
                    @if($f['g'] > 0)
                        <span class="text-gray-500 dark:text-bambu-text-dim shrink-0">{{ round($f['g'], 1) }} g</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <dl class="px-3 py-2 grid grid-cols-[auto,1fr] gap-x-3 gap-y-1 text-xs">
        @foreach($hoverRows as $label => $value)
            <dt class="text-gray-400 dark:text-bambu-text-dim whitespace-nowrap">{{ $label }}</dt>
            <dd class="text-gray-700 dark:text-bambu-text text-right break-words">{{ $value }}</dd>
        @endforeach
    </dl>
</div>
