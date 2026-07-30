<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Printer;
use App\Services\BambuMqttService;
use Illuminate\Console\Command;
use App\Services\NotificationService;
use App\Services\HmsService;
use Carbon\Carbon;

class MqttListenCommand extends Command
{
    protected $signature   = 'mqtt:listen';
    protected $description = 'Naslouchá MQTT zprávám od BambuLab tiskáren a ukládá stav do DB';

    /** @var array<int, int> Poslední zalogovaný interval (v celých 10s) pro každou tiskárnu - zabraňuje duplicitním logům */
    private array $lastAmsKickLogBucket = [];

    public function handle(): int
    {
        if (!Module::isEnabled('mqtt_connector')) {
            $this->warn('MQTT konektor není aktivován. Ukončuji.');
            return 0;
        }

        $this->info('Spouštím MQTT listener...');

        while (true) {
            if (!Module::isEnabled('mqtt_connector')) {
                $this->warn('MQTT konektor byl deaktivován. Ukončuji.');
                return 0;
            }

            $printers = Printer::where('enabled', true)->get();

            if ($printers->isEmpty()) {
                $this->warn('Žádné aktivní tiskárny. Čekám 30s...');
                sleep(30);
                continue;
            }

            foreach ($printers as $printer) {
                $this->info('Připojuji se k: ' . $printer->name);

                $mqtt = new BambuMqttService($printer);

		if (!$mqtt->connect()) {
                    $this->warn('[' . now()->format('H:i:s') . '] ' . $printer->name . ' nedostupná. Čekám 30s...');
                    sleep(30);
                    continue 2;
                }

                $this->info('Připojeno k: ' . $printer->name);
                $mqtt->requestStatus();
                $lastPushallAt = time();

                $mqtt->subscribe(function (array $data) use ($printer, $mqtt) {
                    $this->processMessage($printer, $data, $mqtt);
                });

                try {
                    while (true) {
                        if (!Module::isEnabled('mqtt_connector')) {
                            $this->warn('MQTT konektor byl deaktivován. Ukončuji.');
                            $mqtt->disconnect();
                            return 0;
                        }
                        $mqtt->getClient()->loop(true, 5);

                        // Pravidelně (každých 5 min) znovu požádat o kompletní
                        // status (pushall) - některá pole (např. teplota
                        // komory) chodí jen v kompletní odpovědi, ne v běžných
                        // průběžných status zprávách. Bez opakování bychom je
                        // dostali jen jednou za připojení (klidně jednou za
                        // několik dní, pokud spojení drží).
                        if (time() - $lastPushallAt >= 300) {
                            $mqtt->requestStatus();
                            $lastPushallAt = time();
                        }
                    }
                } catch (\Exception $e) {
                    $this->error('Spojení přerušeno: ' . $e->getMessage() . '. Rekonektuji za 15s.');
                    $mqtt->disconnect();
                    sleep(15);
                }
            }
        }
    }

    private function processMessage(Printer $printer, array $data, BambuMqttService $mqtt): void
    {
        $status = $printer->status ?? [];

        if (isset($data['print'])) {
            $print = $data['print'];

            if (isset($print['gcode_state']))           $status['gcode_state']           = $print['gcode_state'];
            if (isset($print['mc_percent']))             $status['mc_percent']             = $print['mc_percent'];
            if (isset($print['mc_remaining_time']))      $status['mc_remaining_time']      = $print['mc_remaining_time'];
            if (isset($print['subtask_name']))           $status['subtask_name']           = $print['subtask_name'];
            if (isset($print['layer_num']))              $status['layer_num']              = $print['layer_num'];
            if (isset($print['total_layer_num']))        $status['total_layer_num']        = $print['total_layer_num'];
            if (isset($print['print_error']))            $status['print_error']            = $print['print_error'];
            if (isset($print['subtask_id']))             $status['subtask_id']             = $print['subtask_id'];
            if (isset($print['task_id']))                $status['task_id']                = $print['task_id'];
            if (isset($print['gcode_file']))             $status['gcode_file']             = $print['gcode_file'];
            if (isset($print['print_type']))             $status['print_type']             = $print['print_type'];
            if (isset($print['wifi_signal']))            $status['wifi_signal']            = $print['wifi_signal'];
            if (isset($print['spd_mag']))                $status['spd_mag']                = $print['spd_mag'];
            if (isset($print['spd_lvl']))                $status['spd_lvl']                = $print['spd_lvl'];

            if (isset($print['nozzle_temper']) || isset($print['bed_temper'])) {
                $status['temperatures'] = array_merge($status['temperatures'] ?? [], [
                    'nozzle_temper'        => $print['nozzle_temper']        ?? ($status['temperatures']['nozzle_temper']        ?? null),
                    'nozzle_target_temper' => $print['nozzle_target_temper'] ?? ($status['temperatures']['nozzle_target_temper'] ?? null),
                    'bed_temper'           => $print['bed_temper']           ?? ($status['temperatures']['bed_temper']           ?? null),
                    'bed_target_temper'    => $print['bed_target_temper']    ?? ($status['temperatures']['bed_target_temper']    ?? null),
                ]);
            }

            // Teplota komory se mezi modely liší v tom, kde ji tiskárna
            // posílá: některé (možná X1C) ji dávaj přímo jako print.chamber_temper,
            // jiné (ověřeno na X1E) ji mají vnořenou v print.device.ctc.info.temp.
            // Zkusíme nejdřív přímé pole, pak vnořené - ať appka funguje
            // spolehlivě napříč modely.
            if (isset($print['chamber_temper']) && is_numeric($print['chamber_temper'])) {
                $status['temperatures']['chamber_temper'] = (float) $print['chamber_temper'];
            } elseif (isset($print['device']['ctc']['info']['temp']) && is_numeric($print['device']['ctc']['info']['temp'])) {
                // Hodnota může být "zakódovaná" jako (cíl * 65536 + aktuální),
                // pokud přesáhne 500 - v tom případě vezmeme jen zbytek po
                // 65536 jako aktuální teplotu.
                $ctcTemp = (float) $print['device']['ctc']['info']['temp'];
                $status['temperatures']['chamber_temper'] = $ctcTemp > 500 ? fmod($ctcTemp, 65536) : $ctcTemp;
            }

            if (isset($print['cooling_fan_speed'])) {
                $status['fans'] = [
                    'cooling_fan_speed' => $print['cooling_fan_speed'],
                    'big_fan1_speed'    => $print['big_fan1_speed'] ?? null,
                    'big_fan2_speed'    => $print['big_fan2_speed'] ?? null,
                ];
            }

            if (isset($print['ams']))          $status['ams']    = $print['ams'];
            // ams_status: kombinovaná hodnota - horních 8 bitů = hlavní stav
            // (0=nečinná, 1=probíhá výměna filamentu, 2=čtení RFID, ...),
            // spodních 8 bitů = podstav. tray_now samo o sobě nestačí -
            // může se nastavit na cílový slot dřív, než AMS operaci skutečně
            // dokončí, což appce dřív způsobovalo start tisku uprostřed
            // ještě neukončené výměny filamentu.
            if (isset($print['ams_status'])) {
                $rawAmsStatus = is_numeric($print['ams_status']) ? (int) $print['ams_status'] : 0;
                $status['ams_status_main'] = ($rawAmsStatus >> 8) & 0xFF;
                $status['ams_status_sub']  = $rawAmsStatus & 0xFF;
            }
            if (isset($print['lights_report'])) $status['lights'] = $print['lights_report'];
            if (isset($print['vt_tray']))       $status['vt_tray'] = $print['vt_tray'];
            if (isset($print['hms']))           $status['hms']    = $print['hms'];
        }

	// Detekce změn stavu pro notifikace
        $prevState = $printer->status['gcode_state'] ?? '';
        $newState  = $status['gcode_state'] ?? '';
        $prevHms   = $printer->status['hms'] ?? [];
        $newHms    = $status['hms'] ?? [];

        $notifier = new NotificationService($printer);

        // Dokončení tisku
        if ($prevState !== 'FINISH' && $newState === 'FINISH') {
            $filename = $status['subtask_name'] ?? 'neznámý soubor';
            $notifier->printDone($filename);
        }

        // Selhání tisku
        if ($prevState !== 'FAILED' && $newState === 'FAILED') {
            $filename = $status['subtask_name'] ?? 'neznámý soubor';
            $notifier->printFailed($filename);
        }

        // HMS varování – nové chyby
        if (!empty($newHms) && $newHms !== $prevHms) {
            $notifier->hmsError($newHms);
        }

        // Konec filamentu
        $prevAmsStatus = $printer->status['ams']['tray_now'] ?? null;
        $newAmsStatus  = $status['ams']['tray_now'] ?? null;
        if ($newAmsStatus === '255' && $prevAmsStatus !== '255') {
            $notifier->filamentRunout();
        }

        $printer->update([
            'status'       => $status,
            'last_seen_at' => now(),
        ]);

        $this->checkPendingAmsKick($printer, $status, $mqtt);

        $this->line('[' . now()->format('H:i:s') . '] ' . $printer->name . ': ' . ($status['gcode_state'] ?? 'unknown'));
    }

    /**
     * Pokud appka po startu tisku naplánovala AMS "kick" (viz FileDetailPage::confirmPrint()),
     * zkontroluje, jestli tryska už dosáhla cílové teploty, a pokud ano, pošle
     * explicitní ams_change_filament příkaz. Nahrazuje dřívější slepé "sleep(3)"
     * spolehlivější kontrolou skutečného stavu tiskárny.
     */
    private function checkPendingAmsKick(Printer $printer, array $status, BambuMqttService $mqtt): void
    {
        // Znovu načíst čerstvě z DB - webový request mohl pending_ams_kick
        // zapsat mezitím, co tenhle dlouhoběžící proces drží starší instanci.
        $pending = Printer::where('id', $printer->id)->value('pending_ams_kick');
        if (!$pending) {
            return;
        }

        $pending = is_string($pending) ? json_decode($pending, true) : $pending;
        if (!is_array($pending) || !isset($pending['ams_id'], $pending['slot_id'], $pending['requested_at'])) {
            return;
        }

        $requestedAt = Carbon::parse($pending['requested_at']);
        // (int) cast je nutný - diffInSeconds() může vracet float, a intdiv()
        // níže by na float vyhodil TypeError (mimo try/catch, tiše by spadlo
        // celé zpracování zprávy - přesně tohle způsobilo, že se kick nikdy
        // neodeslal, i když teplota správně dosáhla cíle).
        $elapsedSeconds = (int) $requestedAt->diffInSeconds(now());

        // Bezpečnostní timeout - prodloužen na 10 minut. Před samotným
        // přepnutím AMS proběhne spousta kroků (ohřev podložky s M190
        // čekáním, homing, mechanické kontroly), které snadno zaberou
        // víc než pár minut, než tryska vůbec začne stoupat na finální
        // tiskovou teplotu.
        if ($elapsedSeconds > 600) {
            $printer->update(['pending_ams_kick' => null]);
            unset($this->lastAmsKickLogBucket[$printer->id]);
            $this->warn('[' . now()->format('H:i:s') . '] ' . $printer->name . ': naplánovaný AMS kick vypršel (timeout 10 min).');
            return;
        }

        $nozzleTemp   = $status['temperatures']['nozzle_temper'] ?? null;
        $nozzleTarget = $status['temperatures']['nozzle_target_temper'] ?? null;

        // Průběžný log každých ~10s, ať je vidět, na čem se čeká (místo
        // úplného ticha až do timeoutu nebo úspěchu). Bucket zabraňuje
        // opakovanému logování při více zprávách během stejné vteřiny.
        $bucket = intdiv($elapsedSeconds, 10);
        if (($this->lastAmsKickLogBucket[$printer->id] ?? -1) !== $bucket) {
            $this->lastAmsKickLogBucket[$printer->id] = $bucket;
            $this->line(
                '[' . now()->format('H:i:s') . '] ' . $printer->name .
                ': čekám na AMS kick - tryska ' . ($nozzleTemp ?? '?') . '°C / cíl ' . ($nozzleTarget ?? '?') . '°C' .
                ' (uplynulo ' . $elapsedSeconds . 's)'
            );
        }

        if ($nozzleTemp === null || $nozzleTarget === null || $nozzleTarget <= 0) {
            return;
        }

        // Tolerance 5 °C - čekat přesně na cíl by trvalo zbytečně dlouho
        if ($nozzleTemp < $nozzleTarget - 5) {
            return;
        }

        // Odesíláme přes JIŽ OTEVŘENÉ spojení ($mqtt, stejné, co poslouchá
        // status zprávy), ne přes nové připojení (PrinterCommandService by
        // otevřelo druhé, souběžné MQTT spojení ke stejné tiskárně - to se
        // ukázalo jako nespolehlivé, blokovalo zpracování na ~10s a chybu
        // to nevyhodilo jako zachytitelnou výjimku).
        $trayId = (int) $pending['ams_id'] * 4 + (int) $pending['slot_id'];
        $payload = [
            'print' => [
                'sequence_id' => (string) time(),
                'command'     => 'ams_change_filament',
                'ams_id'      => (int) $pending['ams_id'],
                'slot_id'     => (int) $pending['slot_id'],
                'target'      => $trayId,
                'curr_temp'   => -1,
                'tar_temp'    => -1,
            ],
        ];

        $result = $mqtt->publish($payload, 1);

        $printer->update(['pending_ams_kick' => null]);
        unset($this->lastAmsKickLogBucket[$printer->id]);

        $this->info(
            '[' . now()->format('H:i:s') . '] ' . $printer->name .
            ': AMS kick odeslán (výsledek: ' . var_export($result, true) . ', tryska ' . $nozzleTemp . '°C / cíl ' . $nozzleTarget . '°C)'
        );
    }
}
