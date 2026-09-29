<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

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
     *
     * Vrací ['ok' => bool, 'output' => string]. Úspěch se pozná podle
     * návratového kódu příkazu, ne podle textu výstupu (composer/npm
     * chyby často neobsahují žádné jednoznačné klíčové slovo).
     */
    public function runUpdateStep(string $step, string $targetVersion): array
    {
        $path = config('bambups.app_path');

        $command = match ($step) {
            'fetch'    => 'git fetch --tags 2>&1',
            // -f: adresář appky spravuje updater - lokální změny trackovaných
            // souborů (typicky package-lock.json přepsaný npm) nesmí update zablokovat.
            'checkout' => 'git checkout -f ' . escapeshellarg($targetVersion) . ' 2>&1',
            'composer' => 'composer install --no-dev --optimize-autoloader --no-interaction 2>&1',
            // npm ci instaluje přesně podle package-lock.json a nepřepisuje ho.
            'npm'      => 'npm ci --no-audit --no-fund 2>&1 && npm run build 2>&1',
            'migrate'  => 'php artisan migrate --force 2>&1',
            'cache'    => 'php artisan config:clear 2>&1 && php artisan cache:clear 2>&1 && php artisan view:clear 2>&1',
            default    => null,
        };

        if ($command === null) {
            return ['ok' => false, 'output' => "Neznámý krok aktualizace: {$step}"];
        }

        // PHP-FPM spouští příkazy s vyčištěným prostředím (clear_env) - bez HOME
        // composer vůbec nenaběhne a npm neví, kam dát cache. Dáme jim vlastní
        // zapisovatelný domov uvnitř storage/ (patří www-data, jako celá appka).
        $home = $path . '/storage/app/.update-home';
        if (!is_dir($home)) {
            @mkdir($home, 0775, true);
        }

        try {
            $result = Process::path($path)
                ->env([
                    'HOME'             => $home,
                    'COMPOSER_HOME'    => $home . '/.composer',
                    'npm_config_cache' => $home . '/.npm',
                    'PATH'             => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
                ])
                ->timeout(600)
                ->run($command);
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }

        return [
            'ok'     => $result->successful(),
            'output' => trim($result->output() . "\n" . $result->errorOutput()),
        ];
    }
}
