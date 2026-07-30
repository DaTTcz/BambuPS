<?php
namespace App\Livewire;
use App\Models\Printer;
use App\Services\CameraProvisionService;
use Livewire\Component;
class PrinterManager extends Component
{
    public bool $showModal = false;
    public bool $editing = false;
    public ?int $editingId = null;
    public string $name          = '';
    public string $model         = '';
    public string $serial_number = '';
    public string $ip_address    = '';
    public string $access_code   = '';
    public bool   $enabled       = true;

    // Stav progress checklistu po uložení
    public bool $showProvisionModal   = false;
    public ?int $provisioningPrinterId = null;
    public bool $provisioningEnabling  = true;
    public bool $provisioningWasEdit   = false;
    public array $provisionSteps  = [];
    public array $provisionStatus = [];

    protected array $rules = [
        'name'          => 'required|string|max:255',
        'model'         => 'required|string|max:255',
        'serial_number' => 'required|string|max:255',
        'ip_address'    => 'required|ip',
        'access_code'   => 'required|string|max:255',
    ];
    public function getPrintersProperty()
    {
        return Printer::orderBy('name')->get();
    }
    public function openCreate(): void
    {
        $this->reset(['name', 'model', 'serial_number', 'ip_address', 'access_code', 'editingId']);
        $this->enabled  = true;
        $this->editing  = false;
        $this->showModal = true;
    }
    public function openEdit(int $id): void
    {
        $printer = Printer::findOrFail($id);
        $this->editingId      = $id;
        $this->name           = $printer->name;
        $this->model          = $printer->model;
        $this->serial_number  = $printer->serial_number;
        $this->ip_address     = $printer->ip_address;
        $this->access_code    = $printer->access_code;
        $this->enabled        = $printer->enabled;
        $this->editing        = true;
        $this->showModal      = true;
    }

    /**
     * Kroky pro zapnutou (aktivní) tiskárnu - kamera se má nastavit a spustit.
     */
    protected function enableSteps(): array
    {
        return [
            'go2rtc_config'     => 'Aktualizuji go2rtc konfiguraci',
            'supervisor_conf'   => 'Vytvářím konfiguraci kamery',
            'reload_supervisor' => 'Načítám supervisor',
            'restart_go2rtc'    => 'Restartuji go2rtc',
            'restart_camera'    => 'Spouštím kameru tiskárny',
        ];
    }

    /**
     * Kroky pro vypnutou (neaktivní) tiskárnu - kamera se má zastavit.
     */
    protected function disableSteps(): array
    {
        return [
            'stop_camera'       => 'Zastavuji kameru',
            'reload_supervisor' => 'Načítám supervisor',
            'go2rtc_config'     => 'Aktualizuji go2rtc konfiguraci',
            'restart_go2rtc'    => 'Restartuji go2rtc',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $wasEditing = $this->editing && $this->editingId;

        if ($wasEditing) {
            $printer = Printer::findOrFail($this->editingId);
            $printer->update([
                'name'          => $this->name,
                'model'         => $this->model,
                'serial_number' => $this->serial_number,
                'ip_address'    => $this->ip_address,
                'access_code'   => $this->access_code,
                'enabled'       => $this->enabled,
            ]);
        } else {
            $printer = Printer::create([
                'name'          => $this->name,
                'model'         => $this->model,
                'serial_number' => $this->serial_number,
                'ip_address'    => $this->ip_address,
                'access_code'   => $this->access_code,
                'enabled'       => $this->enabled,
            ]);
        }

        // Zavřít formulářové okno a otevřít progress checklist
        $this->showModal = false;

        $this->provisioningPrinterId = $printer->id;
        $this->provisioningEnabling  = $printer->enabled;
        $this->provisioningWasEdit   = $wasEditing;
        $this->provisionSteps  = $printer->enabled ? $this->enableSteps() : $this->disableSteps();
        $this->provisionStatus = array_fill_keys(array_keys($this->provisionSteps), 'pending');
        $this->showProvisionModal = true;
    }

    /**
     * Provede jeden krok provisioningu kamery. Volá se postupně z frontendu (Alpine),
     * takže uživatel vidí živý průběh místo toho, aby okno "viselo".
     */
    public function runProvisionStep(string $step): void
    {
        if (!$this->provisioningPrinterId) {
            return;
        }

        $printer = Printer::find($this->provisioningPrinterId);
        if (!$printer) {
            $this->provisionStatus[$step] = 'done';
            return;
        }

        $service = new CameraProvisionService();

        match ($step) {
            'go2rtc_config'     => $service->regenerateGo2rtcConfig(),
            'supervisor_conf'   => $service->writeCameraSupervisorConf($printer),
            'reload_supervisor' => $service->reloadSupervisor(),
            'restart_go2rtc'    => $service->restartGo2rtc(),
            'restart_camera'    => $service->restartCamera($printer),
            'stop_camera'       => $service->stopCamera($printer->id),
            default             => null,
        };

        $this->provisionStatus[$step] = 'done';
    }

    /**
     * Zavolá frontend, jakmile projdou všechny kroky.
     */
    public function finishProvisioning(): void
    {
        $printer = Printer::find($this->provisioningPrinterId);

        $this->showProvisionModal    = false;
        $this->provisioningPrinterId = null;
        $this->provisionSteps        = [];
        $this->provisionStatus       = [];

        if ($printer) {
            $action = $this->provisioningWasEdit ? 'aktualizována' : 'přidána';
            $cameraNote = $this->provisioningEnabling ? ' Kamera je připravená.' : ' Kamera zastavena.';
            $this->dispatch('toast', type: 'success', message: "Tiskárna {$action}.{$cameraNote}");
        }
    }

    public function toggleEnabled(int $id): void
    {
        $printer = Printer::findOrFail($id);
        $printer->update(['enabled' => !$printer->enabled]);
        $cameraProvision = new CameraProvisionService();

        if ($printer->enabled) {
            $cameraProvision->syncPrinterCamera($printer);
        } else {
            $cameraProvision->removePrinterCamera($printer->id);
        }

        $this->dispatch('toast',
            type: $printer->enabled ? 'success' : 'error',
            message: ($printer->enabled ? 'Tiskárna aktivována: ' : 'Tiskárna deaktivována: ') . $printer->name
        );
    }
    public function delete(int $id): void
    {
        Printer::findOrFail($id)->delete();
        (new CameraProvisionService())->removePrinterCamera($id);
        $this->dispatch('toast', type: 'success', message: 'Tiskárna smazána.');
    }
    public function render()
    {
        return view('livewire.printer-manager');
    }
}
