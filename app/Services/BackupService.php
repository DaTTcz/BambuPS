<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

/**
 * Zálohy databáze appky (storage/app/backups/*.sql.gz).
 *
 * Záloha se dělá automaticky před každou změnou verze (aktualizace i návrat
 * ke starší verzi) a před obnovou jiné zálohy; ručně jde vytvořit ze
 * stránky Nastavení → Verze. Uchovává se posledních MAX_BACKUPS záloh.
 *
 * Podporuje MariaDB/MySQL (mariadb-dump / mysqldump) a SQLite (kopie souboru).
 * Přihlašovací údaje se předávají přes dočasný --defaults-extra-file, ne na
 * příkazové řádce (heslo by bylo vidět v `ps`).
 */
class BackupService
{
    public const MAX_BACKUPS = 10;

    /** Povolený tvar názvu souboru zálohy (ochrana před path traversal) */
    private const NAME_PATTERN = '/^bambups_\d{8}_\d{6}_[A-Za-z0-9._-]+\.(sql|sqlite)\.gz$/';

    public function directory(): string
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        return $dir;
    }

    /**
     * Vytvoří zálohu. $reason se propíše do názvu souboru (např. "pred-v0.2.0").
     * @return array{ok: bool, output: string, name?: string}
     */
    public function create(string $reason = 'rucni'): array
    {
        $version = (new UpdateService())->getCurrentVersion();
        $name = sprintf(
            'bambups_%s_%s_%s',
            now()->format('Ymd_His'),
            $this->slug($version),
            $this->slug($reason)
        );

        $conn   = config('database.default');
        $cfg    = config("database.connections.{$conn}");
        $driver = $cfg['driver'] ?? '';

        try {
            if ($driver === 'sqlite') {
                $result = $this->dumpSqlite($cfg, $name . '.sqlite.gz');
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                $result = $this->dumpMysql($cfg, $name . '.sql.gz');
            } else {
                return ['ok' => false, 'output' => "Nepodporovaný typ databáze: {$driver}"];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }

        if ($result['ok']) {
            $this->prune();
        }

        return $result;
    }

    /**
     * Obnoví databázi ze zálohy. Před obnovou automaticky zazálohuje
     * současný stav, ať jde krok vrátit.
     * @return array{ok: bool, output: string}
     */
    public function restore(string $name): array
    {
        $path = $this->pathFor($name);
        if (!$path) {
            return ['ok' => false, 'output' => 'Záloha nenalezena.'];
        }

        $safety = $this->create('pred-obnovou');
        if (!$safety['ok']) {
            return ['ok' => false, 'output' => 'Nepodařilo se zazálohovat současný stav, obnova zrušena: ' . $safety['output']];
        }

        $conn    = config('database.default');
        $cfg     = config("database.connections.{$conn}");
        $appPath = config('bambups.app_path', base_path());
        $artisan = fn (string $cmd) => Process::path($appPath)->timeout(600)->run('php artisan ' . $cmd . ' 2>&1');

        // MQTT listener během obnovy zastavit - jinak by zapisoval do
        // tabulek, které právě mažeme a nahráváme znovu.
        $mqttWasRunning = str_contains((string) shell_exec('sudo supervisorctl status bambups-mqtt 2>&1'), 'RUNNING');
        if ($mqttWasRunning) {
            shell_exec('sudo supervisorctl stop bambups-mqtt 2>&1');
        }

        try {
            if (str_ends_with($name, '.sqlite.gz')) {
                $db = $cfg['database'] ?? '';
                $import = Process::timeout(300)->run([
                    'bash', '-c', 'set -o pipefail; gunzip -c ' . escapeshellarg($path) . ' > ' . escapeshellarg($db),
                ]);
            } else {
                // Nejdřív smazat všechny tabulky - jinak by v databázi zůstaly
                // tabulky novější verze (např. print_jobs) a migrace by je pak
                // zkoušela vytvořit znovu.
                $wipe = $artisan('db:wipe --force');
                if (!$wipe->successful()) {
                    return ['ok' => false, 'output' => 'Smazání tabulek před obnovou selhalo: ' . $wipe->output()];
                }

                $bin          = $this->findBinary(['mariadb', 'mysql']);
                $defaultsFile = $this->writeDefaultsFile($cfg);
                try {
                    $import = Process::timeout(600)->run([
                        'bash', '-c',
                        'set -o pipefail; gunzip -c ' . escapeshellarg($path)
                        . ' | ' . escapeshellarg($bin) . ' --defaults-extra-file=' . escapeshellarg($defaultsFile)
                        . ' ' . escapeshellarg($cfg['database']),
                    ]);
                } finally {
                    @unlink($defaultsFile);
                }
            }

            if (!$import->successful()) {
                return [
                    'ok'     => false,
                    'output' => 'Import zálohy selhal (současný stav je v záloze ' . ($safety['name'] ?? '?') . '): '
                        . trim($import->errorOutput()),
                ];
            }

            // Záloha mohla vzniknout ve starší verzi - doplnit chybějící
            // tabulky/sloupce pro právě běžící kód.
            $migrate = $artisan('migrate --force');
            $artisan('cache:clear');

            return [
                'ok'     => $migrate->successful(),
                'output' => trim($migrate->output()),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        } finally {
            if ($mqttWasRunning) {
                shell_exec('sudo supervisorctl start bambups-mqtt 2>&1');
            }
        }
    }

    /**
     * Seznam záloh, nejnovější první.
     * @return array<int, array{name: string, size: int, created_at: \Carbon\Carbon, version: string, reason: string}>
     */
    public function list(): array
    {
        $items = [];
        foreach (glob($this->directory() . '/bambups_*.gz') ?: [] as $path) {
            $name = basename($path);
            if (!preg_match(self::NAME_PATTERN, $name)) {
                continue;
            }
            // bambups_YYYYmmdd_HHMMSS_<verze>_<duvod>.sql.gz
            $parts   = explode('_', preg_replace('/\.(sql|sqlite)\.gz$/', '', $name), 5);
            $items[] = [
                'name'       => $name,
                'size'       => filesize($path) ?: 0,
                'created_at' => \Carbon\Carbon::createFromFormat('Ymd His', ($parts[1] ?? '') . ' ' . ($parts[2] ?? '')) ?: now(),
                'version'    => $parts[3] ?? '?',
                'reason'     => str_replace('-', ' ', $parts[4] ?? ''),
            ];
        }

        usort($items, fn ($a, $b) => strcmp($b['name'], $a['name']));
        return $items;
    }

    public function delete(string $name): bool
    {
        $path = $this->pathFor($name);
        return $path ? @unlink($path) : false;
    }

    /** Bezpečná cesta k záloze podle názvu, nebo null */
    public function pathFor(string $name): ?string
    {
        if (!preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }
        $path = $this->directory() . '/' . $name;
        return is_file($path) ? $path : null;
    }

    private function prune(): void
    {
        foreach (array_slice($this->list(), self::MAX_BACKUPS) as $old) {
            $this->delete($old['name']);
        }
    }

    private function dumpMysql(array $cfg, string $file): array
    {
        $bin    = $this->findBinary(['mariadb-dump', 'mysqldump']);
        $target = $this->directory() . '/' . $file;
        $defaultsFile = $this->writeDefaultsFile($cfg);

        try {
            $result = Process::timeout(600)->run([
                'bash', '-c',
                'set -o pipefail; ' . escapeshellarg($bin)
                . ' --defaults-extra-file=' . escapeshellarg($defaultsFile)
                . ' --single-transaction --quick --routines --no-tablespaces --add-drop-table '
                . escapeshellarg($cfg['database'])
                . ' | gzip -6 > ' . escapeshellarg($target),
            ]);
        } finally {
            @unlink($defaultsFile);
        }

        if (!$result->successful()) {
            @unlink($target);
            return ['ok' => false, 'output' => trim($result->errorOutput()) ?: 'Záloha databáze selhala.'];
        }

        return ['ok' => true, 'output' => 'Záloha uložena: ' . $file, 'name' => $file];
    }

    private function dumpSqlite(array $cfg, string $file): array
    {
        $db     = $cfg['database'] ?? '';
        $target = $this->directory() . '/' . $file;
        if (!is_file($db)) {
            return ['ok' => false, 'output' => "SQLite databáze nenalezena: {$db}"];
        }

        $result = Process::timeout(300)->run([
            'bash', '-c', 'set -o pipefail; gzip -6 -c ' . escapeshellarg($db) . ' > ' . escapeshellarg($target),
        ]);

        return $result->successful()
            ? ['ok' => true, 'output' => 'Záloha uložena: ' . $file, 'name' => $file]
            : ['ok' => false, 'output' => trim($result->errorOutput()) ?: 'Záloha databáze selhala.'];
    }

    /** Dočasný soubor s přihlašovacími údaji (jen pro vlastníka) */
    private function writeDefaultsFile(array $cfg): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bambups_db_');
        chmod($path, 0600);

        $quote = fn ($v) => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $v) . '"';
        $lines = ['[client]', 'user=' . $quote($cfg['username'] ?? '')];
        if (($cfg['password'] ?? '') !== '') {
            $lines[] = 'password=' . $quote($cfg['password']);
        }
        if (!empty($cfg['unix_socket'])) {
            $lines[] = 'socket=' . $quote($cfg['unix_socket']);
        } else {
            $lines[] = 'host=' . $quote($cfg['host'] ?? '127.0.0.1');
            $lines[] = 'port=' . (int) ($cfg['port'] ?? 3306);
        }

        file_put_contents($path, implode("\n", $lines) . "\n");
        return $path;
    }

    private function findBinary(array $candidates): string
    {
        foreach ($candidates as $bin) {
            foreach (['/usr/bin/', '/usr/local/bin/'] as $dir) {
                if (is_executable($dir . $bin)) {
                    return $dir . $bin;
                }
            }
        }
        throw new \RuntimeException('Nenalezen nástroj pro zálohu databáze (' . implode(' / ', $candidates) . ').');
    }

    private function slug(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9.-]+/', '-', $value);
        return trim($value, '-') ?: 'x';
    }
}
