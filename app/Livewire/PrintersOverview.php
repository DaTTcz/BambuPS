<?php

namespace App\Livewire;

use App\Models\Printer;
use App\Livewire\Concerns\ControlsPrinterLight;
use Livewire\Component;

class PrintersOverview extends Component
{
    use ControlsPrinterLight;

    public function pausePrint(int $printerId): void
    {
        $printer = Printer::findOrFail($printerId);
        $success = $printer->driver()->pause();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? 'Tisk pozastaven' : 'Chyba');
    }

    public function resumePrint(int $printerId): void
    {
        $printer = Printer::findOrFail($printerId);
        $success = $printer->driver()->resume();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? 'Tisk obnoven' : 'Chyba');
    }

    public function stopPrint(int $printerId): void
    {
        $printer = Printer::findOrFail($printerId);
        $success = $printer->driver()->stop();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? 'Tisk zastaven' : 'Chyba');
    }

    public function render()
    {
        return view('livewire.printers-overview', [
            'printers' => Printer::where('enabled', true)->get(),
        ]);
    }
}
