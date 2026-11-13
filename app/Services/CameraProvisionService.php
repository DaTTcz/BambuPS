<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Printer;

class CameraProvisionService
{
    protected string $supervisorConfDir = '/etc/supervisor/conf.d';
    protected string $go2rtcYamlPath    = '/opt/bambups/go2rtc/go2rtc.yaml';
    protected string $logDir            = '/var/log';

    /**
     * Přegeneruje go2rtc.yaml podle všech aktivních tiskáren.
     */
    public function regenerateGo2rtcConfig(): void
    {
        $config  = Module::getConfig('go2rtc');
        $apiPort = $config['api_port'] ?? 1984;
        $printers = Printer::where('enabled', true)->get();

        $streams = '';
        foreach ($printers as $printer) {
            // Jednoduché uvozovky v YAML: URL může obsahovat ?, & apod.
            $source   = str_replace("'", "''", $printer->cameraSource());
            $streams .= "  printer_{$printer->id}: '{$source}'\n";
        }

        $yaml = "api:\n  listen: :{$apiPort}\n\nrtsp:\n  listen: :8554\n\nstreams:\n{$streams}";

        file_put_contents($this->go2rtcYamlPath, $yaml);
    }

    /**
     * Vytvoří/aktualizuje supervisor program pro camera:capture danné tiskárny.
     */
    public function writeCameraSupervisorConf(Printer $printer): void
    {
        // camera:capture (HLS/snapshot přes ffmpeg -c:v copy) je jen pro Bambu
        // H.264 stream. Vlastní kamera (např. MJPEG) ho nepotřebuje - snímky
        // bere go2rtc nebo přímo URL snímku.
        if ($printer->hasCustomCamera()) {
            $this->removeCameraSupervisorConf($printer->id);
            return;
        }

        $name = "bambups-camera-{$printer->id}";
        $conf = "[program:{$name}]\n"
            . "command=php /opt/bambups/app/artisan camera:capture {$printer->id}\n"
            . "directory=/opt/bambups/app\n"
            . "autostart=true\n"
            . "autorestart=true\n"
            . "stopasgroup=true\n"
            . "killasgroup=true\n"
            . "user=www-data\n"
            . "numprocs=1\n"
            . "redirect_stderr=true\n"
            . "stdout_logfile={$this->logDir}/{$name}.log\n"
            . "stdout_logfile_maxbytes=5MB\n"
            . "stdout_logfile_backups=2\n"
            . "startsecs=3\n"
            . "startretries=5\n";

        file_put_contents("{$this->supervisorConfDir}/{$name}.conf", $conf);
    }

    /**
     * Smaže supervisor program pro camera:capture danné tiskárny (podle ID).
     */
    public function removeCameraSupervisorConf(int $printerId): void
    {
        $name = "bambups-camera-{$printerId}";
        $path = "{$this->supervisorConfDir}/{$name}.conf";

        if (file_exists($path)) {
            shell_exec("sudo supervisorctl stop {$name} 2>&1");
            @unlink($path);
        }
    }

    /**
     * Řekne supervisoru, aby si znovu načetl configy z disku a aplikoval změny.
     */
    public function reloadSupervisor(): void
    {
        shell_exec('sudo supervisorctl reread 2>&1');
        shell_exec('sudo supervisorctl update 2>&1');
    }

    /**
     * Restartuje go2rtc daemon.
     */
    public function restartGo2rtc(): void
    {
        shell_exec('sudo supervisorctl restart bambups-go2rtc 2>&1');
    }

    /**
     * Restartuje camera:capture daemon danné tiskárny.
     */
    public function restartCamera(Printer $printer): void
    {
        if ($printer->hasCustomCamera()) {
            return;
        }
        shell_exec("sudo supervisorctl restart bambups-camera-{$printer->id} 2>&1");
    }

    /**
     * Zastaví camera:capture daemon danné tiskárny (podle ID), config nechá být.
     */
    public function stopCamera(int $printerId): void
    {
        shell_exec("sudo supervisorctl stop bambups-camera-{$printerId} 2>&1");
    }

    /**
     * Kompletní synchronizace po vytvoření/úpravě tiskárny:
     * - přegeneruje go2rtc.yaml
     * - vytvoří/aktualizuje supervisor conf pro její kameru
     * - restartuje potřebné démony
     */
    public function syncPrinterCamera(Printer $printer): void
    {
        $this->regenerateGo2rtcConfig();
        $this->writeCameraSupervisorConf($printer);
        $this->reloadSupervisor();

        shell_exec('sudo supervisorctl restart bambups-go2rtc 2>&1');

        $name = "bambups-camera-{$printer->id}";
        shell_exec("sudo supervisorctl restart {$name} 2>&1");
    }

    /**
     * Kompletní úklid před/po smazání tiskárny:
     * - zastaví a odstraní supervisor program pro její kameru
     * - přegeneruje go2rtc.yaml bez ní
     */
    public function removePrinterCamera(int $printerId): void
    {
        $this->removeCameraSupervisorConf($printerId);
        $this->reloadSupervisor();
        $this->regenerateGo2rtcConfig();
        shell_exec('sudo supervisorctl restart bambups-go2rtc 2>&1');
    }
}
