<?php

namespace App\Livewire;

use App\Services\BackupService;
use App\Services\UpdateService;
use Livewire\Component;

/**
 * Nastavení → Verze a zálohy.
 *
 * Samotný přechod na verzi neprovádí tahle komponenta, ale VersionBadge
 * v horním menu (událost start-version-change) - ta existuje ve všech
 * verzích od v0.1.10, takže kroky po checkoutu dokončí i starší kód.
 */
class VersionManager extends Component
{
    public ?string $confirmRestore = null;
    public ?string $restoreError   = null;

    public function getCurrentVersionProperty(): string
    {
        return (new UpdateService())->getCurrentVersion();
    }

    public function getReleasesProperty(): array
    {
        return (new UpdateService())->getReleases();
    }

    public function getBackupsProperty(): array
    {
        return (new BackupService())->list();
    }

    public function refreshReleases(): void
    {
        (new UpdateService())->clearVersionCache();
        $this->dispatch('toast', type: 'success', message: 'Seznam verzí obnoven.');
    }

    public function switchTo(string $version): void
    {
        $this->dispatch('start-version-change', version: $version)->to(VersionBadge::class);
    }

    public function createBackup(): void
    {
        $result = (new BackupService())->create('rucni');
        $this->dispatch('toast',
            type: $result['ok'] ? 'success' : 'error',
            message: $result['ok'] ? 'Záloha databáze vytvořena.' : 'Záloha selhala: ' . $result['output']
        );
    }

    public function askRestore(string $name): void
    {
        $this->confirmRestore = $name;
        $this->restoreError   = null;
    }

    public function cancelRestore(): void
    {
        $this->confirmRestore = null;
        $this->restoreError   = null;
    }

    public function restore(): void
    {
        if (!$this->confirmRestore) {
            return;
        }

        $result = (new BackupService())->restore($this->confirmRestore);

        if (!$result['ok']) {
            $this->restoreError = $result['output'] !== '' ? $result['output'] : 'Obnova selhala.';
            return;
        }

        $this->confirmRestore = null;
        $this->dispatch('toast', type: 'success', message: 'Databáze obnovena ze zálohy.');
        // Obnovou se mohly přepsat i relace přihlášení - čistý reload
        $this->redirect(route('versions.settings'));
    }

    public function deleteBackup(string $name): void
    {
        $ok = (new BackupService())->delete($name);
        $this->dispatch('toast', type: $ok ? 'success' : 'error', message: $ok ? 'Záloha smazána.' : 'Zálohu se nepodařilo smazat.');
    }

    public function render()
    {
        return view('livewire.version-manager');
    }
}
