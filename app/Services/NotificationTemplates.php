<?php

namespace App\Services;

/**
 * Šablony notifikací - události, dostupné proměnné, výchozí texty a vykreslení.
 *
 * Vlastní šablony se ukládají ke kanálu do notification_settings.config:
 *   "templates": { "print_start": { "body": "...", "subject": "...", "image": true }, ... }
 * Chybějící klíč = výchozí hodnota z EVENTS.
 *
 * Syntaxe: {proměnná}. Řádek, ve kterém jsou VŠECHNY proměnné prázdné, se
 * vynechá - např. řádek "🧵 {filament}" zmizí, když tisk nemá údaj o filamentu.
 * Telegram umí HTML (<b>, <i>, <code>); u e-mailu a MQTT se značky odstraní.
 */
class NotificationTemplates
{
    public const CHANNELS = ['email', 'telegram', 'mqtt'];

    public const EVENTS = [
        'print_start' => [
            'label'   => 'Začátek tisku',
            'icon'    => '🖨️',
            'trigger' => 'on_print_start',
            'suffix'  => 'OnStart',
            'image'   => true,
            'subject' => 'BambuPS – Tisk začal – {printer}',
            'body'    => "🖨️ <b>Tisk začal</b> na {printer}\n📄 {file} {plate}\n⏱ {estimated} · hotovo kolem {eta}\n🧵 {filament}\n🛏️ {bed}\n📐 {layers}",
            'vars'    => ['printer', 'file', 'plate', 'estimated', 'eta', 'filament', 'bed', 'layers', 'date', 'time'],
        ],
        'print_done' => [
            'label'   => 'Dokončení tisku',
            'icon'    => '✅',
            'trigger' => 'on_print_done',
            'suffix'  => 'OnDone',
            'image'   => true,
            'subject' => 'BambuPS – Tisk dokončen – {printer}',
            'body'    => "✅ <b>Tisk dokončen</b> na {printer}\n📄 {file} {plate}\n⏱ {duration} {estimated_note}\n🧵 {filament}",
            'vars'    => ['printer', 'file', 'plate', 'duration', 'estimated', 'estimated_note', 'filament', 'date', 'time'],
        ],
        'print_failed' => [
            'label'   => 'Selhání tisku',
            'icon'    => '❌',
            'trigger' => 'on_print_failed',
            'suffix'  => 'OnFailed',
            'image'   => true,
            'subject' => 'BambuPS – {result} – {printer}',
            'body'    => "{result_icon} <b>{result}</b> na {printer}\n📄 {file} {plate}\n📊 {progress}\n⚠️ {error}",
            'vars'    => ['printer', 'file', 'plate', 'result', 'result_icon', 'progress', 'duration', 'error', 'date', 'time'],
        ],
        'hms_error' => [
            'label'   => 'HMS varování',
            'icon'    => '⚠️',
            'trigger' => 'on_hms_error',
            'suffix'  => 'OnHms',
            'image'   => false,
            'subject' => 'BambuPS – Varování tiskárny – {printer}',
            'body'    => "⚠️ <b>Varování tiskárny</b> {printer}\n{errors}",
            'vars'    => ['printer', 'errors', 'error', 'date', 'time'],
        ],
        'filament_runout' => [
            'label'   => 'Konec filamentu',
            'icon'    => '🧵',
            'trigger' => 'on_filament_runout',
            'suffix'  => 'OnFilament',
            'image'   => false,
            'subject' => 'BambuPS – Konec filamentu – {printer}',
            'body'    => "🧵 <b>Konec filamentu</b> v tiskárně {printer}",
            'vars'    => ['printer', 'file', 'date', 'time'],
        ],
    ];

    /** Popisky proměnných pro editor */
    public const VAR_LABELS = [
        'printer'        => 'Název tiskárny',
        'file'           => 'Název souboru / úlohy',
        'plate'          => 'Deska, např. „(deska 2)“ – jen u více desek',
        'estimated'      => 'Odhad doby tisku',
        'estimated_note' => '„(odhad 1h 30m)“',
        'eta'            => 'Čas dokončení, např. 14:35',
        'duration'       => 'Skutečná doba tisku',
        'filament'       => 'Filament, např. „27,0 g PLA“',
        'bed'            => 'Podložka a teplota',
        'layers'         => 'Vrstvy a výška vrstvy',
        'result'         => '„Tisk selhal“ / „Tisk zrušen“',
        'result_icon'    => '❌ / ⏹',
        'progress'       => 'Průběh a vrstva při konci',
        'error'          => 'První HMS chyba',
        'errors'         => 'Všechny HMS chyby (každá na řádku)',
        'date'           => 'Datum',
        'time'           => 'Čas',
    ];

    /** Ukázkové hodnoty pro náhled a zkušební odeslání */
    public const SAMPLE = [
        'printer'        => 'Bambu X1C',
        'file'           => 'Bat-0.4nz_0.20lh-27g-PLA',
        'plate'          => '(deska 2)',
        'estimated'      => '1h 45m',
        'estimated_note' => '(odhad 1h 45m)',
        'eta'            => '01:14',
        'duration'       => '1h 52m',
        'filament'       => '27,0 g PLA',
        'bed'            => 'Textured PEI Plate · 55 °C',
        'layers'         => '150 vrstev · 0.2 mm',
        'result'         => 'Tisk selhal',
        'result_icon'    => '❌',
        'progress'       => '43 % · vrstva 65/150',
        'error'          => 'Filament se zasekl v extruderu',
        'errors'         => "Filament se zasekl v extruderu\nTeplota trysky mimo rozsah",
        'date'           => '30.10.2026',
        'time'           => '23:04',
    ];

    public static function event(string $event): array
    {
        return self::EVENTS[$event] ?? [];
    }

    /** Šablona kanálu pro událost (vlastní, jinak výchozí) */
    public static function forChannel(?array $config, string $event): array
    {
        $def    = self::event($event);
        $custom = $config['templates'][$event] ?? [];

        return [
            'body'    => ($custom['body'] ?? '') !== '' ? $custom['body'] : ($def['body'] ?? ''),
            'subject' => ($custom['subject'] ?? '') !== '' ? $custom['subject'] : ($def['subject'] ?? ''),
            'image'   => array_key_exists('image', $custom) ? (bool) $custom['image'] : (bool) ($def['image'] ?? false),
            'custom'  => !empty($custom),
        ];
    }

    /**
     * Vykreslí šablonu. $html = true -> hodnoty proměnných se escapují
     * (Telegram HTML), jinak se ze šablony odstraní HTML značky.
     */
    public static function render(string $template, array $vars, bool $html): string
    {
        if (!$html) {
            $template = html_entity_decode(strip_tags($template), ENT_QUOTES);
        }

        $out = [];
        foreach (preg_split('/\r?\n/', $template) as $line) {
            $found = 0;
            $empty = 0;
            $rendered = preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($vars, $html, &$found, &$empty) {
                if (!array_key_exists($m[1], $vars)) {
                    return $m[0]; // neznámá proměnná zůstane, ať je překlep vidět
                }
                $found++;
                $value = trim((string) $vars[$m[1]]);
                if ($value === '') {
                    $empty++;
                }
                return $html ? htmlspecialchars($value, ENT_QUOTES) : $value;
            }, $line);

            if ($found > 0 && $found === $empty) {
                continue; // řádek bez jakýchkoliv údajů
            }
            $out[] = rtrim(preg_replace('/ {2,}/', ' ', $rendered));
        }

        return trim(implode("\n", $out));
    }
}
