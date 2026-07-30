<?php

namespace App\Livewire;

use App\Models\Printer;
use App\Services\PrinterCommandService;
use Livewire\Component;

class PrintersOverview extends Component
{
    public function pausePrint(int $printerId): void
    {
        $printer = Printer::findOrFail($printerId);
        $success = (new PrinterCommandService($printer))->pause();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? 'Tisk pozastaven' : 'Chyba');
    }

    public function resumePrint(int $printerId): void
    {
        $printer = Printer::findOrFail($printerId);
        $success = (new PrinterCommandService($printer))->resume();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? 'Tisk obnoven' : 'Chyba');
    }

    public function stopPrint(int $printerId): void
    {
        $printer = Printer::findOrFail($printerId);
        $success = (new PrinterCommandService($printer))->stop();
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
