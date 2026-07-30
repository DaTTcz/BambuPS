<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class UpdateService
{
    /**
     * Aktuálně nainstalovaná verze - poslední git tag na HEAD.
     * Pokud appka nemá žádný tag (např. čerstvý clone bez release),
     * vrátí zkrácený hash aktuálního commitu jako fallback.
     */
    public function getCurrentVersion(): string
    {
        $path = config('bambups.app_path');

        $tag = trim((string) shell_exec("cd " . escapeshellarg($path) . " && git describe --tags --abbrev=0 2>/dev/null"));
        if ($tag !== '') {
            return $tag;
        }

        $hash = trim((string) shell_exec("cd " . escapeshellarg($path) . " && git rev-parse --short HEAD 2>/dev/null"));
        return $hash !== '' ? $hash : 'neznámá';
    }

    /**
     * Nejnovější dostupný release na GitHubu (cachováno na 1 hodinu,
     * aby appka nezatěžovala GitHub API při každém načtení stránky).
     */
    public function getLatestVersion(): ?string
    {
        return Cache::remember('bambups:latest_version', 3600, function () {
            $repo = config('bambups.github_repo');

            try {
                $response = Http::withHeaders(['Accept' => 'application/vnd.github+json'])
                    ->timeout(5)
                    ->get("https://api.github.com/repos/{$repo}/releases/latest");

                if ($response->successful()) {
                    return $response->json('tag_name');
                }
            } catch (\Throwable $e) {
                // Bez internetu / GitHub nedostupný - appka dál funguje, jen bez info o update
            }

            return null;
        });
    }

    /**
     * Je dostupná novější verze než ta nainstalovaná?
     */
    public function isUpdateAvailable(): bool
    {
        $current = $this->normalizeVersion($this->getCurrentVersion());
        $latest  = $this->getLatestVersion();

        if (!$latest) {
            return false;
        }

        $latest = $this->normalizeVersion($latest);

        // Pokud aktuální verze není platné semver (např. hash commitu bez tagu),
        // nemůžeme spolehlivě porovnat - raději neukazovat update jako "dostupný".
        if (!preg_match('/^\d+\.\d+\.\d+$/', $current)) {
            return false;
        }

        return version_compare($latest, $current, '>');
    }

    private function normalizeVersion(string $version): string
    {
        return ltrim($version, 'vV');
    }

    public function clearVersionCache(): void
    {
        Cache::forget('bambups:latest_version');
    }

    /**
     * Provede jeden krok aktualizace. Volá se postupně z frontendu (stejný
     * vzor jako CameraProvisionService::runProvisionStep), aby uživatel
     * viděl živý průběh aktualizace.
     */
    public function runUpdateStep(string $step, string $targetVersion): string
    {
        $path = config('bambups.app_path');
        $cd   = "cd " . escapeshellarg($path);

        return match ($step) {
            'fetch'    => (string) shell_exec("{$cd} && git fetch --tags 2>&1"),
            'checkout' => (string) shell_exec("{$cd} && git checkout " . escapeshellarg($targetVersion) . " 2>&1"),
            'composer' => (string) shell_exec("{$cd} && composer install --no-dev --optimize-autoloader --no-interaction 2>&1"),
            'npm'      => (string) shell_exec("{$cd} && npm install 2>&1 && npm run build 2>&1"),
            'migrate'  => (string) shell_exec("{$cd} && php artisan migrate --force 2>&1"),
            'cache'    => (string) shell_exec("{$cd} && php artisan config:clear 2>&1 && php artisan cache:clear 2>&1 && php artisan view:clear 2>&1"),
            default    => '',
        };
    }
}
