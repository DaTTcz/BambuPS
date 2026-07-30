<?php

namespace App\Livewire;

use App\Models\Printer;
use App\Services\PrinterCommandService;
use Livewire\Component;

class PrinterDetail extends Component
{
    public Printer $printer;

    public int $targetHotend     = 0;
    public int $targetBed        = 0;
    public int $targetSpeed      = 100;
    public int $targetFanCooling = 0;
    public int $targetFanAux     = 0;
    public int $targetFanFilter  = 0;
    public float $jogDistance    = 10;
    public string $jogCustom     = '';
    public bool $showStopConfirm   = false;
    public bool $showPauseConfirm  = false;
    public bool $showResumeConfirm = false;

    public function refreshPrinter(): void
    {
        $this->printer = Printer::find($this->printer->id);
    }

    public function mount(Printer $printer): void
    {
        $this->printer          = $printer;
        $this->targetHotend     = (int) ($printer->status['temperatures']['nozzle_target_temper'] ?? 0);
        $this->targetBed        = (int) ($printer->status['temperatures']['bed_target_temper'] ?? 0);
        $this->targetSpeed      = (int) ($printer->status['spd_mag'] ?? 100);
        $this->targetFanCooling = (int) round(($printer->status['fans']['cooling_fan_speed'] ?? 0) / 15 * 100);
        $this->targetFanAux     = (int) round(($printer->status['fans']['big_fan1_speed'] ?? 0) / 15 * 100);
        $this->targetFanFilter  = (int) round(($printer->status['fans']['big_fan2_speed'] ?? 0) / 15 * 100);
    }

    private function cmd(): PrinterCommandService
    {
        return new PrinterCommandService($this->printer);
    }

    private function refresh(): void
    {
        sleep(1);
        $this->printer->refresh();
    }

    public function toggleLight(string $node): void
    {
        $lights  = $this->printer->status['lights'] ?? [];
        $current = collect($lights)->firstWhere('node', $node);
	$currentMode = $current['mode'] ?? 'off';
        $newMode = in_array($currentMode, ['on', 'flashing']) ? 'off' : 'on';
        $success = $this->cmd()->setLight($node, $newMode);
        if ($success) {
            $this->refresh();
            $this->dispatch('toast', type: 'success',
                message: 'Světlo ' . ($newMode === 'on' ? 'zapnuto' : 'vypnuto'));
        } else {
            $this->dispatch('toast', type: 'error', message: 'Chyba při ovládání světla');
        }
    }

    public function applyHotendTemp(): void
    {
        $success = $this->cmd()->setHotendTemp($this->targetHotend);
        if ($success) $this->refresh();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? "Tryska nastavena na {$this->targetHotend}°C" : 'Chyba');
    }

    public function hotendOff(): void
    {
        $this->targetHotend = 0;
        $this->cmd()->setHotendTemp(0);
        $this->refresh();
        $this->dispatch('toast', type: 'success', message: 'Tryska vypnuta');
    }

    public function applyBedTemp(): void
    {
        $success = $this->cmd()->setBedTemp($this->targetBed);
        if ($success) $this->refresh();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? "Podložka nastavena na {$this->targetBed}°C" : 'Chyba');
    }

    public function bedOff(): void
    {
        $this->targetBed = 0;
        $this->cmd()->setBedTemp(0);
        $this->refresh();
        $this->dispatch('toast', type: 'success', message: 'Podložka vypnuta');
    }

    public function applySpeed(): void
    {
        $success = $this->cmd()->setPrintSpeed($this->targetSpeed);
        if ($success) $this->refresh();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? "Rychlost nastavena na {$this->targetSpeed}%" : 'Chyba');
    }

    public function applyFanCooling(): void
    {
        $success = $this->cmd()->setFan(1, $this->targetFanCooling);
        if ($success) $this->refresh();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? "Ventilátor {$this->targetFanCooling}%" : 'Chyba');
    }

    public function applyFanAux(): void
    {
        $success = $this->cmd()->setFan(2, $this->targetFanAux);
        if ($success) $this->refresh();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? "Aux ventilátor {$this->targetFanAux}%" : 'Chyba');
    }

    public function applyFanFilter(): void
    {
        $success = $this->cmd()->setFan(3, $this->targetFanFilter);
        if ($success) $this->refresh();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? "Filtr ventilátor {$this->targetFanFilter}%" : 'Chyba');
    }

    public function confirmPause(): void
    {
        $this->showPauseConfirm = true;
    }

    public function confirmStop(): void
    {
        $this->showStopConfirm = true;
    }

    public function pausePrint(): void
    {
        $this->showPauseConfirm = false;
        $success = $this->cmd()->pause();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? 'Tisk pozastaven' : 'Chyba');
    }

    public function stopPrint(): void
    {
        $this->showStopConfirm = false;
        $success = $this->cmd()->stop();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? 'Tisk zastaven' : 'Chyba');
    }

    public function confirmResume(): void
    {
        $this->showResumeConfirm = true;
    }

    public function resumePrint(): void
    {
        $this->showResumeConfirm = false;
        $success = $this->cmd()->resume();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? 'Tisk obnoven' : 'Chyba');
    }

    public function homeAll(): void
    {
        $success = $this->cmd()->homeAll();
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? 'Home spuštěn' : 'Chyba');
    }

    public function homeAxis(string $axis): void
    {
        $success = $this->cmd()->homeAxis($axis);
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? "Home {$axis}" : 'Chyba');
    }

    public function moveAxis(string $axis, float $distance): void
    {
        $speed   = str_starts_with($axis, 'Z') ? 300 : 3000;
        $success = $this->cmd()->moveAxis($axis, $distance, $speed);
        $this->dispatch('toast', type: $success ? 'success' : 'error',
            message: $success ? "Pohyb {$axis} {$distance}mm" : 'Chyba');
    }

    public function setJogFromCustom(): void
    {
        $val = (float) $this->jogCustom;
        if ($val > 0 && $val <= 200) {
            $this->jogDistance = $val;
        }
    }

    public function applyPreset(string $preset): void
    {
        $presets = [
            'pla'  => ['hotend' => 220, 'bed' => 65],
            'petg' => ['hotend' => 240, 'bed' => 80],
            'abs'  => ['hotend' => 260, 'bed' => 100],
            'tpu'  => ['hotend' => 230, 'bed' => 40],
            'cool' => ['hotend' => 0,   'bed' => 0],
        ];

        if (!isset($presets[$preset])) return;

        $p = $presets[$preset];
        $this->cmd()->setHotendTemp($p['hotend']);
        $this->cmd()->setBedTemp($p['bed']);
        $this->targetHotend = $p['hotend'];
        $this->targetBed    = $p['bed'];
        $this->refresh();

        $this->dispatch('toast', type: 'success',
            message: $preset === 'cool' ? 'Chlazení spuštěno' : 'Předvolba ' . strtoupper($preset) . ' nastavena');
    }

    public function render()
    {
	return view('livewire.printer-detail', [
            'temperatures' => $this->printer->status['temperatures'] ?? [],
            'fans'         => $this->printer->status['fans'] ?? [],
        ]);
    }
}
