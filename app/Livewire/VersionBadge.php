<?php

namespace App\Livewire;

use App\Services\UpdateService;
use Livewire\Attributes\On;
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
            'backup'   => 'Zálohuji databázi',
            'fetch'    => 'Stahuji seznam verzí z GitHubu',
            'checkout' => 'Přepínám na novou verzi',
            'composer' => 'Aktualizuji PHP závislosti',
            'npm'      => 'Přebuilduji frontend',
            'migrate'  => 'Aktualizuji databázi',
            'cache'    => 'Čistím cache',
            'services' => 'Restartuji služby na pozadí',
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

    /**
     * Přechod na konkrétní verzi (i starší) ze stránky Nastavení → Verze.
     * Běží stejnými kroky jako běžná aktualizace - po checkoutu je dál
     * vykonává už kód cílové verze, proto nesmí přibýt žádné nové public
     * vlastnosti (starší verze komponenty by je při hydrataci neznala).
     */
    #[On('start-version-change')]
    public function startUpdateTo(string $version): void
    {
        $service = new UpdateService();
        if (!$service->isSwitchableVersion($version)) {
            $this->dispatch('toast', type: 'error', message: "Na verzi {$version} nejde přejít.");
            return;
        }
        if ($version === $service->getCurrentVersion()) {
            $this->dispatch('toast', type: 'success', message: "Verze {$version} už je nainstalovaná.");
            return;
        }

        $this->targetVersion   = $version;
        $this->updateSteps     = $this->updateStepList();
        $this->updateStatus    = array_fill_keys(array_keys($this->updateSteps), 'pending');
        $this->updateFailed    = false;
        $this->updateError     = '';
        $this->showUpdateModal = true;
    }

    public function runUpdateStep(string $step): void
    {
        if ($this->updateFailed) {
            return;
        }

        $result = (new UpdateService())->runUpdateStep($step, $this->targetVersion);

        // Selhání poznáme podle návratového kódu příkazu (viz UpdateService).
        if (!$result['ok']) {
            $this->updateFailed = true;
            $this->updateError  = $result['output'] !== '' ? $result['output'] : 'Krok skončil chybou bez dalšího výpisu.';
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

    /**
     * Ruční kontrola aktualizací - obchází hodinovou cache dostupnou přes
     * getLatestVersion(). Použije se tlačítkem vedle čísla verze, ať uživatel
     * nemusí čekat, až cache sama vyprší.
     */
    public function checkForUpdates(): void
    {
        $service = new UpdateService();
        $service->clearVersionCache();

        if ($service->isUpdateAvailable()) {
            // Nic dalšího není potřeba - tlačítko "aktualizuj" se objeví samo
            // přes computed property getUpdateAvailableProperty().
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Appka je aktuální (' . $service->getCurrentVersion() . ').');
    }

    public function render()
    {
        return view('livewire.version-badge');
    }
}
