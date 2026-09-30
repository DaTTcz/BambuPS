<?php

namespace App\Livewire\Concerns;

use App\Models\Printer;
use App\Services\PrinterCommandService;

/**
 * Přepínání světla komory přímo z karty tiskárny (dashboard, přehled tiskáren).
 * Detail tiskárny má vlastní ovládání všech světel (PrinterDetail::toggleLight).
 */
trait ControlsPrinterLight
{
    public function toggleChamberLight(int $printerId): void
    {
        $printer = Printer::findOrFail($printerId);

        $light   = collect($printer->status['lights'] ?? [])->firstWhere('node', 'chamber_light');
        $isOn    = in_array($light['mode'] ?? 'off', ['on', 'flashing'], true);
        $newMode = $isOn ? 'off' : 'on';

        $success = (new PrinterCommandService($printer))->setLight('chamber_light', $newMode);

        if ($success) {
            // Tiskárna nový stav nahlásí přes MQTT za chvilku - počkáme,
            // ať karta po překreslení ukáže už aktuální stav tlačítka.
            sleep(1);
        }

        $this->dispatch('toast',
            type: $success ? 'success' : 'error',
            message: $success ? ('Světlo ' . ($newMode === 'on' ? 'zapnuto' : 'vypnuto')) : 'Chyba při ovládání světla'
        );
    }
}
