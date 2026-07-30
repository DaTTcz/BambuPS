<?php

namespace App\Livewire;

use App\Services\UpdateService;
use Livewire\Component;

class VersionBadge extends Component
{
    public bool $showUpdateModal = false;
    public array $updateSteps  = [];
    public array $updateStatus = [];
    public string $targetVersion = '';
    public bool $updateFailed = false;
    public string $updateError = '';

    public function getCurrentVersionProperty(): string
    {
        return (new UpdateService())->getCurrentVersion();
    }

    public function getLatestVersionProperty(): ?string
    {
        return (new UpdateService())->getLatestVersion();
    }

    public function getUpdateAvailableProperty(): bool
    {
        return (new UpdateService())->isUpdateAvailable();
    }

    protected function updateStepList(): array
    {
        return [
            'fetch'    => 'Stahuji seznam verzí z GitHubu',
            'checkout' => 'Přepínám na novou verzi',
            'composer' => 'Aktualizuji PHP závislosti',
            'npm'      => 'Přebuilduji frontend',
            'migrate'  => 'Aktualizuji databázi',
            'cache'    => 'Čistím cache',
        ];
    }

    public function startUpdate(): void
    {
        $latest = (new UpdateService())->getLatestVersion();
        if (!$latest) {
            return;
        }

        $this->targetVersion = $latest;
        $this->updateSteps    = $this->updateStepList();
        $this->updateStatus   = array_fill_keys(array_keys($this->updateSteps), 'pending');
        $this->updateFailed   = false;
        $this->updateError    = '';
        $this->showUpdateModal = true;
    }

    public function runUpdateStep(string $step): void
    {
        if ($this->updateFailed) {
            return;
        }

        $output = (new UpdateService())->runUpdateStep($step, $this->targetVersion);

        // Jednoduchá detekce selhání - fatal chyby PHP/composeru/gitu obsahují tato klíčová slova
        if (preg_match('/fatal:|error:|Fatal error|Your requirements could not be resolved/i', $output)) {
            $this->updateFailed = true;
            $this->updateError  = $output;
            return;
        }

        $this->updateStatus[$step] = 'done';
    }

    public function finishUpdate(): void
    {
        (new UpdateService())->clearVersionCache();

        if (!$this->updateFailed) {
            $this->showUpdateModal = false;
            $this->dispatch('toast', type: 'success', message: "Appka aktualizována na {$this->targetVersion}.");
        }
    }

    public function closeUpdateModal(): void
    {
        $this->showUpdateModal = false;
    }

    public function render()
    {
        return view('livewire.version-badge');
    }
}
