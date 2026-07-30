<?php

namespace App\Livewire;

use App\Models\Module;
use App\Services\CameraProvisionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ModuleManager extends Component
{
    public ?int $editingId = null;
    public array $editingConfig = [];
    public string $newTokenName = '';
    public ?string $newTokenValue = null;

    public function getModulesProperty()
    {
        return Module::orderBy('name')->get();
    }

    public function getSlicerTokensProperty()
    {
        return Auth::user()->tokens()
            ->where('name', 'like', 'Slicer:%')
            ->get();
    }

    public function getMqttStatusProperty(): string
    {
        $output = shell_exec('sudo supervisorctl status bambups-mqtt 2>&1');
        if (str_contains($output, 'RUNNING')) return 'running';
        if (str_contains($output, 'STOPPED')) return 'stopped';
        if (str_contains($output, 'EXITED'))  return 'stopped';
        return 'unknown';
    }

    public function getGo2rtcStatusProperty(): string
    {
        $output = shell_exec('sudo supervisorctl status bambups-go2rtc 2>&1');
        if (str_contains($output, 'RUNNING')) return 'running';
        if (str_contains($output, 'STOPPED')) return 'stopped';
        if (str_contains($output, 'EXITED'))  return 'stopped';
        return 'unknown';
    }

    public function toggle(int $id): void
    {
        $module = Module::findOrFail($id);
        $module->update(['enabled' => !$module->enabled]);
        $cameraProvision = new CameraProvisionService();

        if ($module->name === 'mqtt_connector') {
            if ($module->enabled) {
                shell_exec('sudo supervisorctl start bambups-mqtt 2>&1');
            } else {
                shell_exec('sudo supervisorctl stop bambups-mqtt 2>&1');
            }
        }

        if ($module->name === 'go2rtc') {
            if ($module->enabled) {
                $cameraProvision->regenerateGo2rtcConfig();
                shell_exec('sudo supervisorctl start bambups-go2rtc 2>&1');
            } else {
                shell_exec('sudo supervisorctl stop bambups-go2rtc 2>&1');
            }
        }

        $this->dispatch('toast',
            type: $module->enabled ? 'success' : 'error',
            message: ($module->enabled ? 'Modul aktivován: ' : 'Modul deaktivován: ') . $module->label
        );
    }

    public function startEdit(int $id): void
    {
        $module = Module::findOrFail($id);
        $this->editingId     = $id;
        $this->editingConfig = $module->config ?? [];
    }

    public function saveConfig(): void
    {
        $module = Module::findOrFail($this->editingId);
        $module->update(['config' => $this->editingConfig]);
        $cameraProvision = new CameraProvisionService();

        if ($module->name === 'mqtt_connector' && $module->enabled) {
            shell_exec('sudo supervisorctl restart bambups-mqtt 2>&1');
            $this->dispatch('toast', type: 'success', message: 'Konfigurace uložena, MQTT listener restartován.');
        } elseif ($module->name === 'go2rtc' && $module->enabled) {
            $cameraProvision->regenerateGo2rtcConfig();
            shell_exec('sudo supervisorctl restart bambups-go2rtc 2>&1');
            $this->dispatch('toast', type: 'success', message: 'Konfigurace uložena, go2rtc restartován.');
        } else {
            $this->dispatch('toast', type: 'success', message: 'Konfigurace uložena.');
        }

        $this->editingId = null;
    }

    public function createSlicerToken(): void
    {
        $this->validate(['newTokenName' => 'required|string|max:255'], [
            'newTokenName.required' => 'Název tokenu je povinný.',
        ]);

        $token = Auth::user()->createToken('Slicer:' . $this->newTokenName, ['slicer:upload']);

        $this->newTokenValue = $token->plainTextToken;
        $this->newTokenName  = '';

        $this->dispatch('toast', type: 'success', message: 'Token vytvořen. Zkopíruj ho – zobrazí se jen jednou!');
    }

    public function deleteToken(int $id): void
    {
        Auth::user()->tokens()->where('id', $id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Token smazán.');
    }

    public function render()
    {
        return view('livewire.module-manager');
    }
}
