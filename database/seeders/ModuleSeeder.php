<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'name'    => 'slicer_connector',
                'label'   => 'Slicer konektor',
                'enabled' => false,
                'config'  => [],
            ],
            [
                'name'    => 'mqtt_connector',
                'label'   => 'MQTT konektor',
                'enabled' => false,
                'config'  => [
                    'port'            => 8883,
                    'connect_timeout' => 5,
                    'loop_interval'   => 1,
                ],
            ],
            [
                'name'    => 'go2rtc',
                'label'   => 'Kamera (go2rtc)',
                'enabled' => false,
                'config'  => [
                    'api_port' => 1984,
                ],
            ],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['name' => $module['name']],
                $module
            );
        }
    }
}
