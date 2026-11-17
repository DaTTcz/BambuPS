{{--
    Mapa objektů na desce (pohled shora) - klikem se objekt vynechá / vrátí.
    Parametry:
      $objects  [['id', 'name', 'bbox' => [minX, minY, maxX, maxY]|null], ...]
      $bed      [šířka, hloubka] podložky v mm
      $skipped  int[] ID vynechaných objektů
      $action   název Livewire metody, která dostane ID objektu (např. 'toggleSkipObject')
      $locked   int[] ID, které už nejdou vrátit (vynechané tiskárnou během tisku)
      $confirm  volitelně text potvrzení před kliknutím (wire:confirm)
--}}
@php
    $locked  = $locked ?? [];
    $confirm = $confirm ?? null;
    [$bedW, $bedH] = $bed ?? [256, 256];
    $boxes = array_filter(array_column($objects, 'bbox'));
    // Výřez kolem objektů (s okrajem), ať jsou i malé objekty dobře vidět
    if ($boxes) {
        $minX = max(0, min(array_column($boxes, 0)) - 12);
        $minY = max(0, min(array_column($boxes, 1)) - 12);
        $maxX = min($bedW, max(array_column($boxes, 2)) + 12);
        $maxY = min($bedH, max(array_column($boxes, 3)) + 12);
        $size = max($maxX - $minX, $maxY - $minY, 60);
        $cx = ($minX + $maxX) / 2; $cy = ($minY + $maxY) / 2;
        $vx = $cx - $size / 2; $vy = $bedH - ($cy + $size / 2);
    } else {
        $vx = 0; $vy = 0; $size = max($bedW, $bedH);
    }
    $font = max(3, $size / 22);
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-start">
    @if($boxes)
        <svg viewBox="{{ $vx }} {{ $vy }} {{ $size }} {{ $size }}" class="w-full rounded-lg bg-gray-100 dark:bg-bambu-dark-3 border border-gray-200 dark:border-bambu-dark-4"
            style="max-height: 260px;">
            {{-- podložka --}}
            <rect x="0" y="0" width="{{ $bedW }}" height="{{ $bedH }}" fill="none" stroke="#9ca3af" stroke-width="{{ $size / 200 }}" stroke-dasharray="{{ $size / 60 }}"/>
            @foreach($objects as $n => $o)
                @continue(empty($o['bbox']))
                @php
                    [$x1, $y1, $x2, $y2] = $o['bbox'];
                    $isSkip  = in_array($o['id'], $skipped, true);
                    $isLock  = in_array($o['id'], $locked, true);
                    $rx = $x1; $ry = $bedH - $y2; $rw = max(1, $x2 - $x1); $rh = max(1, $y2 - $y1);
                @endphp
                <g wire:key="obj-map-{{ $o['id'] }}" @if(!$isLock) wire:click="{{ $action }}({{ $o['id'] }})" @if($confirm) wire:confirm="{{ $confirm }}" @endif style="cursor: pointer;" @endif>
                    <title>#{{ $n + 1 }} {{ $o['name'] }}{{ $isSkip ? ' – vynecháno' : '' }}</title>
                    @if(!empty($o['outline']))
                        {{-- Skutečný obrys 1. vrstvy (vnější stěny), díry přes evenodd --}}
                        @php
                            $d = '';
                            foreach ($o['outline'] as $loop) {
                                for ($i = 0, $c = count($loop); $i < $c; $i += 2) {
                                    $d .= ($i === 0 ? 'M' : 'L') . $loop[$i] . ' ' . round($bedH - $loop[$i + 1], 1) . ' ';
                                }
                                $d .= 'Z ';
                            }
                        @endphp
                        <path d="{{ trim($d) }}" fill-rule="evenodd"
                            fill="{{ $isSkip ? '#ef4444' : '#22c55e' }}" fill-opacity="{{ $isSkip ? 0.2 : 0.45 }}"
                            stroke="{{ $isSkip ? '#ef4444' : '#16a34a' }}" stroke-width="{{ $size / 220 }}" stroke-linejoin="round"/>
                    @else
                        <rect x="{{ $rx }}" y="{{ $ry }}" width="{{ $rw }}" height="{{ $rh }}" rx="{{ $size / 120 }}"
                            fill="{{ $isSkip ? '#ef4444' : '#22c55e' }}" fill-opacity="{{ $isSkip ? 0.15 : 0.35 }}"
                            stroke="{{ $isSkip ? '#ef4444' : '#16a34a' }}" stroke-width="{{ $size / 160 }}"/>
                    @endif
                    @if($isSkip)
                        <line x1="{{ $rx }}" y1="{{ $ry }}" x2="{{ $rx + $rw }}" y2="{{ $ry + $rh }}" stroke="#ef4444" stroke-width="{{ $size / 200 }}"/>
                        <line x1="{{ $rx + $rw }}" y1="{{ $ry }}" x2="{{ $rx }}" y2="{{ $ry + $rh }}" stroke="#ef4444" stroke-width="{{ $size / 200 }}"/>
                    @endif
                    <text x="{{ $rx + $rw / 2 }}" y="{{ $ry + $rh / 2 }}" text-anchor="middle" dominant-baseline="central"
                        font-size="{{ $font }}" font-weight="700" fill="{{ $isSkip ? '#fca5a5' : '#f0fdf4' }}"
                        stroke="{{ $isSkip ? '#7f1d1d' : '#14532d' }}" stroke-width="{{ $font / 12 }}" paint-order="stroke"
                        style="pointer-events: none;">{{ $n + 1 }}</text>
                </g>
            @endforeach
        </svg>
    @endif

    <div class="space-y-1 max-h-64 overflow-y-auto {{ $boxes ? '' : 'sm:col-span-2' }}">
        @foreach($objects as $n => $o)
            @php
                $isSkip = in_array($o['id'], $skipped, true);
                $isLock = in_array($o['id'], $locked, true);
            @endphp
            <label wire:key="obj-list-{{ $o['id'] }}" class="flex items-center gap-2 text-sm rounded-lg px-2 py-1 {{ $isLock ? 'opacity-60' : 'cursor-pointer hover:bg-gray-100 dark:hover:bg-bambu-dark-3' }}">
                <input type="checkbox" class="rounded text-green-600" @checked(!$isSkip) @disabled($isLock) onclick="event.preventDefault()"
                    @if(!$isLock) wire:click="{{ $action }}({{ $o['id'] }})" @if($confirm) wire:confirm="{{ $confirm }}" @endif @endif>
                <span class="font-mono text-xs w-6 text-gray-400">#{{ $n + 1 }}</span>
                <span class="truncate {{ $isSkip ? 'line-through text-gray-400' : 'text-gray-700 dark:text-bambu-text' }}">{{ $o['name'] }}</span>
                @if($isLock)<span class="text-xs text-red-500 shrink-0">vynecháno</span>@endif
            </label>
        @endforeach
    </div>
</div>
