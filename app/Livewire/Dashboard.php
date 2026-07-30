<?php

namespace App\Livewire;

use App\Models\File;
use App\Models\Printer;
use App\Services\PrinterCommandService;
use Livewire\Component;

class Dashboard extends Component
{
    public function getPrintersProperty()
    {
        return Printer::where('enabled', true)->orderBy('name')->get();
    }

    public function getRecentFilesProperty()
    {
        return File::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();
    }

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
        return view('livewire.dashboard');
    }
}
