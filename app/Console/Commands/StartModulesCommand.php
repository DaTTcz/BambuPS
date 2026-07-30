<?php

namespace App\Console\Commands;

use App\Models\Module;
use Illuminate\Console\Command;

class StartModulesCommand extends Command
{
    protected $signature   = 'modules:start';
    protected $description = 'Spustí Supervisor daemony podle aktivních modulů v DB';

    public function handle(): int
    {
        $this->info('Kontroluji aktivní moduly...');

        $modules = [
            'mqtt_connector' => 'bambups-mqtt',
            'go2rtc'         => 'bambups-go2rtc',
        ];

        foreach ($modules as $moduleName => $supervisorName) {
            $module = Module::where('name', $moduleName)->first();

            if (!$module) {
                $this->warn("Modul {$moduleName} nenalezen v DB.");
                continue;
            }

            if ($module->enabled) {
                $this->info("Spouštím: {$supervisorName}");
                shell_exec("supervisorctl start {$supervisorName} 2>&1");
            } else {
                $this->line("Modul {$moduleName} není aktivní – přeskakuji.");
            }
        }

        $this->info('Hotovo.');
        return 0;
    }
}
