<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class UpdateService
{
    /** Značka v cache pro "verzi se nepodařilo zjistit" (null Cache neuloží). */
    private const NO_VERSION = '__unavailable__';

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
     * Nejnovější dostupný release na GitHubu. Úspěch se cachuje na 1 hodinu,
     * neúspěch (bez internetu, výpadek GitHubu, vyčerpaný API limit) na
     * 10 minut - Cache::remember() totiž null neukládá, takže by se jinak
     * appka ptala GitHubu (s 5s timeoutem) při každém načtení stránky.
     */
    public function getLatestVersion(): ?string
    {
        $key    = 'bambups:latest_version';
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached === self::NO_VERSION ? null : $cached;
        }

        $repo = config('bambups.github_repo');

        try {
            $response = Http::withHeaders(['Accept' => 'application/vnd.github+json'])
                ->timeout(5)
                ->get("https://api.github.com/repos/{$repo}/releases/latest");

            $tag = $response->successful() ? $response->json('tag_name') : null;

            if (is_string($tag) && $tag !== '') {
                Cache::put($key, $tag, 3600);
                return $tag;
            }
        } catch (\Throwable $e) {
            // Bez internetu / GitHub nedostupný - appka dál funguje, jen bez info o update
        }

        Cache::put($key, self::NO_VERSION, 600);
        return null;
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
            // Démoni pod supervisorem (MQTT listener, camera:capture) drží kód
            // v paměti - bez restartu by dál jeli se starou verzí. Restartujeme
            // jen ty, které právě běží (vypnuté moduly nezapínáme). go2rtc je
            // samostatná binárka, s kódem appky se nemění, takže ho necháme být.
            // Pozn.: `supervisorctl status` vrací nenulový kód, když něco neběží,
            // proto z něj bereme jen výstup a výsledek kroku určí až restart.
            'services' => 'progs=$(sudo supervisorctl status 2>/dev/null'
                . ' | awk \'/^bambups-(mqtt|camera-[0-9]+)[[:space:]]/ && /RUNNING/ {print $1}\');'
                . ' if [ -n "$progs" ]; then sudo supervisorctl restart $progs 2>&1;'
                . ' else echo "Žádné běžící služby k restartu."; fi',
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
