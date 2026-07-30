<?php

namespace App\Services;

class HmsService
{
    /**
     * Modul ID podle oficiální dokumentace:
     * wiki.bambulab.com/en/x1/troubleshooting/intro-hms
     */
    private const MODULE_NAMES = [
        '03' => 'Pohyb',          // MC - Motion Controller (osy, tryska, podložka, komora)
        '05' => 'Systém',         // AP - Main Board (síť, SD karta, obrazovka, MQTT)
        '07' => 'AMS',            // AMS - Automated Material System
        '08' => 'Tisková hlava',  // TH - Tool Head / extrudér
        '0C' => 'Kamera/Lidar',   // XCAM - Micro Lidar, kamery
        '10' => 'Studio',
        '11' => 'Aplikace',
        '12' => 'AMS Lite',
        '18' => 'AMS-HT',         // vysokoteplotní AMS (H2D)
    ];

    /**
     * U těchto modulů druhý bajt "attr" (Module No) označuje POŘADOVÉ ČÍSLO
     * konkrétní jednotky (AMS 1, AMS 2, ...), ne kategorii chyby.
     * Při hledání v tabulce se proto ignoruje (vynuluje) a dopočítá se zvlášť,
     * aby jeden záznam pokryl chybu na libovolné AMS jednotce.
     */
    private const MULTI_UNIT_MODULES = ['07', '12', '18'];

    /**
     * Dekóduje HMS chybu z raw MQTT reportu tiskárny ({'attr' => int, 'code' => int}).
     */
    public static function decode(array $hms): array
    {
        $attr = $hms['attr'] ?? 0;
        $code = $hms['code'] ?? 0;

        $attrHex = sprintf('%08X', $attr);
        $codeHex = sprintf('%08X', $code);

        $moduleId = substr($attrHex, 0, 2);
        $moduleNo = substr($attrHex, 2, 2);

        // Prvních 4 znaky "code" = Alert Level (0000/0001/0002/0003), zbytek = ID chyby
        $alertLevelHex = substr($codeHex, 0, 4);

        $message = self::getMessage($attrHex, $codeHex, $moduleId, $moduleNo);
        $level   = self::getSeverity($alertLevelHex);

        return [
            'attr_hex' => $attrHex,
            'code_hex' => $codeHex,
            'message'  => $message,
            'severity' => $level['label'],
            'color'    => $level['color'],
            'raw'      => $hms,
        ];
    }

    /**
     * Závažnost je zakódovaná v prvních 4 znacích "code" (Alert Level), podle
     * oficiální dokumentace:
     * 0001 = Chyba (tisk se zastaví, musí se odstranit před dalším tiskem)
     * 0002 = Varování (tisk se pozastaví, musí se odstranit před pokračováním)
     * 0003 = Info (jen informativní)
     * 0000 = Neplatné hlášení
     */
    private static function getSeverity(string $alertLevelHex): array
    {
        return match ($alertLevelHex) {
            '0001'  => ['label' => 'Chyba',    'color' => 'red'],
            '0002'  => ['label' => 'Varování', 'color' => 'yellow'],
            '0003'  => ['label' => 'Info',     'color' => 'blue'],
            default => ['label' => 'Neznámá',  'color' => 'gray'],
        };
    }

    private static function getMessage(string $attr, string $code, string $moduleId, string $moduleNo): string
    {
        $lookupAttr = $attr;
        $unitLabel  = '';

        if (in_array($moduleId, self::MULTI_UNIT_MODULES, true)) {
            // Vynulovat číslo jednotky v klíči (aby AMS1/AMS2/AMS3... sdílely stejný popis)
            $lookupAttr = $moduleId . '00' . substr($attr, 4, 4);
            $unitNo     = hexdec($moduleNo) + 1;
            $unitLabel  = " (jednotka {$unitNo})";
        }

        $key = $lookupAttr . '_' . $code;

        if (isset(self::MESSAGES[$key])) {
            return self::MESSAGES[$key] . $unitLabel;
        }

        $moduleName = self::MODULE_NAMES[$moduleId] ?? null;
        if ($moduleName) {
            return "{$moduleName}: Neznámá chyba{$unitLabel} (kód: {$code})";
        }

        return "Neznámá chyba (attr: {$attr}, kód: {$code})";
    }

    /**
     * Ověřené kódy z oficiální Bambu Lab wiki (wiki.bambulab.com/en/hms/home).
     * Klíč = attr(8 hex) . '_' . code(8 hex), přesně jak je počítá decode().
     *
     * U AMS/AMS Lite/AMS-HT (moduly 07/12/18) je číslo jednotky v klíči
     * vynulované (viz getMessage) - popis je společný pro všechny jednotky.
     * U vícenásobných slotů (AMS Slot 1/2/3/4) je zatím pokrytý jen slot 1 -
     * pro slot 2-4 appka spadne na obecnou kategorii "AMS: Neznámá chyba".
     */
    private const MESSAGES = [
        // ---- 0C: Kamera / Micro Lidar (X1 i H2D) ----
        '0C000100_00010001' => 'Micro Lidar kamera je offline.',
        '0C000100_00020002' => 'Micro Lidar kamera je nefunkční.',
        '0C000100_00010003' => 'Chyba synchronizace Micro Lidaru.',
        '0C000100_00010004' => 'Čočka Micro Lidaru je znečištěná – vyčistěte ji.',
        '0C000100_00010005' => 'Chybný OTP parametr Micro Lidaru.',
        '0C000100_00020006' => 'Chybný extrinsic parametr Micro Lidaru.',
        '0C000100_00020007' => 'Laserový parametr Micro Lidaru se rozešel.',
        '0C000100_00020008' => 'Kamera komory je offline.',
        '0C000100_00010009' => 'Kamera komory je znečištěná.',
        '0C000100_0001000A' => 'LED Micro Lidaru je pravděpodobně vadná.',
        '0C000100_0001000B' => 'Kalibrace Micro Lidaru selhala. Zkontrolujte, že kalibrační karta je čistá a nezakrytá.',
        '0C000100_00020017' => 'Čočka kamery trysky je znečištěná – může to ovlivnit AI monitoring.',
        '0C000200_00010001' => 'Laser se nerozsvítil.',
        '0C000200_00020002' => 'Laserová stopa je příliš silná.',
        '0C000200_00020003' => 'Laser není dostatečně jasný.',
        '0C000200_00020004' => 'Výška trysky se zdá příliš nízká.',
        '0C000200_00010005' => 'Byl detekován nový Micro Lidar.',
        '0C000200_00020006' => 'Výška trysky se zdá příliš vysoká.',
        '0C000300_00020001' => 'Měření expozice filamentu selhalo.',
        '0C000300_00020002' => 'Kontrola první vrstvy přerušena kvůli chybným datům z lidaru.',
        '0C000300_00020004' => 'Kontrola první vrstvy není pro tuto úlohu podporována.',
        '0C000300_00020005' => 'Časový limit kontroly první vrstvy vypršel.',
        '0C000300_00030006' => 'Vyčištěné (purge) filamenty se hromadí ve žlábku. Zkontrolujte a vyčistěte ho.',
        '0C000300_00030007' => 'Možná vada první vrstvy.',
        '0C000300_00030008' => 'Možný "špagety" defekt tisku. Zkontrolujte kvalitu tisku.',
        '0C000300_00010009' => 'Modul kontroly první vrstvy byl restartován.',
        '0C000300_0003000B' => 'Probíhá kontrola první vrstvy.',
        '0C000300_0002000C' => 'Značka podložky (build plate marker) nebyla detekována.',
        '0C000300_0002000E' => 'Tryska se zdá být ucpaná zbytky materiálu.',
        '0C000300_00020011' => 'Kalibrace vysoké přesnosti posunu trysky selhala. Zopakujte kalibraci.',
        '0C000300_00020013' => 'Detekce cizích objektů nefunguje. Restartujte zařízení nebo aktualizujte firmware.',
        '0C000300_00020014' => 'Přesnost detekce cizích objektů se snížila. Zkuste kalibraci Live View kamery.',
        '0C000300_00030010' => 'Tiskárna se zdá tisknout bez extruze materiálu.',
        '0C000400_00010005' => 'Chyba kamery BirdsEye – kontaktujte podporu.',
        '0C000400_00020007' => 'Nastavuje se kamera BirdsEye. Odstraňte objekty a podložku, zkontrolujte značku a vyčistěte kamery.',
        '0C000400_00020019' => 'Kamera BirdsEye je nainstalována posunutá. Přeinstalujte podle wiki.',
        '0C000400_00020023' => 'Měření tloušťky selhalo – kamera trysky nerozpoznala povrch materiálu.',
        '0C000400_00020026' => 'Inicializace Liveview kamery selhala, AI funkce (např. detekce špaget) budou vypnuté.',

        // ---- 03: Pohyb / MC (osy, tryska, podložka, komora) ----
        '03000100_0001000A' => 'Řízení teploty podložky je abnormální, AC deska může být vadná.',
        '03000100_00010003' => 'Teplota podložky je abnormální; ohřívač je přehřátý.',
        '03000100_00010001' => 'Teplota podložky je abnormální; topné těleso má zřejmě zkrat.',
        '03000100_00010002' => 'Teplota podložky je abnormální; topné těleso má otevřený obvod, nebo je rozpojená termopojistka.',
        '03000100_00010006' => 'Teplota podložky je abnormální; senzor má zřejmě zkrat.',
        '03000100_00010007' => 'Teplota podložky je abnormální; senzor má otevřený obvod.',
        '03000100_00010008' => 'Chyba během ohřevu podložky; topné moduly mohou být vadné.',
        '03000100_00030008' => 'Teplota podložky přesáhla limit a byla automaticky snížena na limitní hodnotu.',
        '03000100_0001000C' => 'Podložka dlouhodobě pracuje na plný výkon. Systém regulace teploty může být vadný.',
        '03000100_0001000D' => 'Dříve nastala abnormalita v topných modulech podložky. Postupujte podle wiki.',
        '03000100_0001000E' => 'Detekované napětí napájení neodpovídá stroji. Podložka byla deaktivována.',
        '03000200_00010001' => 'Teplota trysky je abnormální, topné těleso má zřejmě zkrat.',
        '03000200_00010002' => 'Teplota trysky je abnormální, topné těleso má otevřený obvod.',
        '03000200_00010003' => 'Teplota trysky je abnormální, přehřátí.',
        '03000200_00010006' => 'Teplota trysky je abnormální, senzor má zřejmě zkrat.',
        '03000200_00010007' => 'Teplota trysky je abnormální, senzor má otevřený obvod.',
        '03000200_00010009' => 'Řízení teploty trysky je abnormální. Hotend zřejmě není nainstalovaný.',
        '03000300_00010001' => 'Chladicí ventilátor hotendu je pomalý nebo stojí. Zkontrolujte konektor.',
        '03000300_00020002' => 'Rychlost ventilátoru hotendu je nízká.',
        '03000400_00020001' => 'Rychlost ventilátoru ofuku modelu je nízká nebo stojí.',
        '03000600_00010001' => 'Motor-A má otevřený obvod. Konektor je uvolněný, nebo je motor vadný.',
        '03000600_00010002' => 'Motor-A má zkrat. Motor je pravděpodobně vadný.',
        '03000600_00010003' => 'Odpor Motoru-A je abnormální, motor je pravděpodobně vadný.',
        '03000900_00010001' => 'Servo motor extruderu má otevřený obvod. Konektor je uvolněný, nebo je motor vadný.',
        '03000900_00010002' => 'Servo motor extruderu má zkrat. Motor je pravděpodobně vadný.',
        '03000900_00010003' => 'Odpor servo motoru extruderu je abnormální; motor je pravděpodobně vadný.',
        '03000900_00020001' => 'Extrudér je přetížený. Může být ucpaný, nebo je filament zaseknutý v tiskové hlavě.',
        '03000900_00020002' => 'Extrudér má abnormální odpor. Může být ucpaný, nebo je filament zaseknutý v tiskové hlavě.',
        '03000900_00020003' => 'Extrudér tiskne abnormálně. Může být ucpaný, nebo je filament příliš tenký a extrudér prokluzuje.',
        '03000A00_00010001' => 'Citlivost snímače síly podložky (1/2/3) je příliš vysoká.',
        '03000A00_00010002' => 'Citlivost snímače síly podložky (1/2/3) je nízká.',
        '03000A00_00010003' => 'Citlivost snímače síly podložky (1/2/3) je příliš nízká.',
        '03000A00_00010004' => 'Na snímači síly podložky bylo detekováno vnější rušení. Podložka se možná něčeho dotkla mimo vyhřívanou plochu.',
        '03000A00_00010005' => 'Snímač síly podložky detekoval neočekávaně trvalou sílu. Podložka může být zaseklá, nebo je vadná elektronika.',
        '03000D00_00010003' => 'Podložka není správně umístěná. Upravte její polohu.',
        '03000D00_00020001' => 'Homing podložky je abnormální: na podložce může být vypoulenina, nebo špička trysky není čistá.',
        '03000D00_0001000B' => 'Motor osy Z se zřejmě zasekl při pohybu. Zkontrolujte cizí předměty na Z tyčích/řemenici.',
        '03000F00_00010001' => 'Detekována abnormální data akcelerometru. Zkuste restartovat tiskárnu.',
        '03001000_00020001' => '1. řád mechanické rezonance osy X je nízký.',
        '03001000_00020002' => 'Rezonanční frekvence osy X se výrazně liší od poslední kalibrace. Vyčistěte uhlíkovou tyč a spusťte kalibraci znovu.',
        '03001200_00020001' => 'Přední kryt tiskové hlavy odpadl.',
        '03001300_00010001' => 'Snímač proudu Motoru-A je abnormální. Může jít o poruchu hardwarového vzorkovacího obvodu.',
        '03001800_00010001' => 'Hodnota snímače extruzní síly je nízká, tryska zřejmě není nainstalovaná.',
        '03001800_00010002' => 'Citlivost snímače extruzní síly je nízká, hotend zřejmě není správně nainstalovaný.',
        '03001800_00010003' => 'Snímač extruzní síly není dostupný. Spojení mezi MC a TH může být přerušené, nebo je snímač vadný.',
        '03001800_00010004' => 'Data ze snímače extruzní síly jsou abnormální, snímač je pravděpodobně vadný.',
        '03001800_00010005' => 'Motor osy Z se zdá zaseknutý při pohybu. Zkontrolujte cizí předměty na Z tyčích/řemenici.',
        '03001800_00010006' => 'Data kalibrace podložky jsou abnormální. Zkontrolujte cizí předměty na podložce a Z tyči.',
        '03001800_00010007' => 'Frekvence snímače extruzní síly je příliš vysoká. Snímač může být vadný, nebo je chladič trysky příliš blízko.',
        '03001800_00010008' => 'Tryska se abnormálně dotýká podložky. Zkontrolujte zbytky filamentu na trysce nebo cizí předměty na podložce.',
        '03001900_00010001' => 'Vířivý (eddy current) senzor osy Y není dostupný, kabel je pravděpodobně přerušený.',
        '03001900_00020002' => 'Citlivost vířivého senzoru osy Y je příliš nízká.',
        '03001A00_00020001' => 'Tryska je omotaná filamentem, nebo je podložka umístěná nesprávně.',
        '03001A00_00020002' => 'Snímač extruzní síly detekoval ucpanou trysku.',
        '03001D00_00010001' => 'Poziční senzor extruzního motoru je abnormální. Spojení k senzoru může být uvolněné.',
        '03002500_00010001' => 'Frekvence snímače extruzní síly pravého extruderu je příliš nízká. Tryska zřejmě není nainstalovaná.',
        '03002500_0001000A' => 'Kalibrace posunu trysky selhala. Filament ulpívá na trysce – vyčistěte ji a zkuste znovu.',
        '03002500_0001000B' => 'Detekce přítomnosti trysky selhala: pravý extrudér – tryska není (správně) nainstalovaná.',
        '03002900_00010001' => 'Vzor enkodéru nelze rozpoznat; možné příčiny: deformace vzoru, přeexponování světlem, špatně umístěná podložka.',
        '03009000_00010001' => 'Ohřev komory selhal. Ohřívač komory zřejmě nefouká horký vzduch.',
        '03009000_00010002' => 'Ohřev komory selhal. Komora není uzavřená, okolní teplota je nízká, nebo je zablokovaný odvětrávací otvor napájení.',
        '03009D00_00020001' => 'Kalibrace ohniska laseru (XY) selhala. Vyčistěte plochu pro homing laseru a zopakujte kalibraci.',
        '03009600_00010001' => 'Přední dvířka jsou zřejmě otevřená; úloha byla pozastavena.',
        '03009700_00010001' => 'Horní kryt je zřejmě otevřený; úloha byla pozastavena.',
        '0300A100_00010001' => 'Teplota komory je příliš vysoká. Otevřete horní kryt a přední dvířka, nebo snižte okolní teplotu.',
        '0300A200_00010001' => 'Teplota MC modulu je příliš vysoká, možná kvůli vysoké teplotě komory.',

        // ---- 05: Systém / Main Board ----
        '05000100_00020001' => 'Mediální pipeline (video) je nefunkční. Zkuste restartovat tiskárnu.',
        '05000100_00020002' => 'USB kamera není připojená.',
        '05000100_00020003' => 'USB kamera je nefunkční.',
        '05000100_00030004' => 'Na SD kartě není dostatek místa.',
        '05000100_00030005' => 'Chyba SD karty.',
        '05000100_00030006' => 'Nezformátovaná SD karta / USB disk.',
        '05000200_00020001' => 'Nepodařilo se připojit k internetu. Zkontrolujte síťové připojení.',
        '05000200_00020002' => 'Přihlášení zařízení selhalo.',
        '05000200_00020004' => 'Neautorizovaný uživatel. Zkontrolujte údaje účtu.',
        '05000200_00020006' => 'Služba Liveview je nefunkční.',
        '05000200_00020008' => 'Synchronizace času selhala.',
        '05000300_00010001' => 'MC modul je nefunkční. Restartujte zařízení nebo zkontrolujte propojení kabelů.',
        '05000300_00010002' => 'Tisková hlava je nefunkční. Restartujte zařízení.',
        '05000300_00010003' => 'Modul AMS je nefunkční. Restartujte zařízení.',
        '05000300_00010004' => 'Modul zásobníku filamentu (buffer) je nefunkční. Restartujte zařízení.',
        '05000300_0001000A' => 'Stav systému je abnormální. Obnovte tovární nastavení.',
        '05000300_0001000B' => 'Obrazovka je nefunkční. Restartujte zařízení.',
        '05000300_0002000C' => 'Chyba WiFi hardwaru: vypněte/zapněte WiFi nebo restartujte zařízení.',
        '05000400_00010001' => 'Nepodařilo se stáhnout tiskovou úlohu. Zkontrolujte připojení k síti.',
        '05000400_00010002' => 'Nepodařilo se nahlásit stav tisku. Zkontrolujte připojení k síti.',
        '05000400_00010003' => 'Obsah tiskového souboru je nečitelný. Odešlete úlohu znovu.',
        '05000400_00010004' => 'Tiskový soubor není autorizovaný.',
        '05000400_00010006' => 'Nepodařilo se obnovit předchozí tisk.',
        '05000400_00020007' => 'Teplota podložky přesahuje bod skelného přechodu filamentu – hrozí ucpání trysky.',
        '05000400_00020042' => 'Live View kamera je znečištěná nebo zakrytá – vyčistěte ji.',
        '05000400_00020043' => 'Čočka kamery tiskové hlavy je znečištěná. Vyčistěte ji.',
        '05000500_00010007' => 'Ověření MQTT příkazu selhalo, aktualizujte Bambu Studio nebo Handy.',
        '05000600_00020001' => 'Kamera tiskové hlavy není na místě. Zkontrolujte hardwarové připojení.',
        '05000600_00020002' => 'Kamera trysky není na místě. Zkontrolujte hardwarové připojení.',
        '05000600_00020004' => 'Live View kamera není na místě. Zkontrolujte hardwarové připojení.',

        // ---- 07 / 12 / 18: AMS, AMS Lite, AMS-HT (číslo jednotky se doplňuje automaticky) ----
        '07000100_00010001' => 'Přiváděcí (assist) motor AMS prokluzuje. Podávací kolečko je opotřebené, nebo je filament příliš tenký.',
        '07000100_00010003' => 'Řízení momentu přiváděcího motoru AMS je vadné. Snímač proudu může být vadný.',
        '07000100_00010004' => 'Řízení rychlosti přiváděcího motoru AMS je vadné. Snímač rychlosti může být vadný.',
        '07000100_00020002' => 'Přiváděcí motor AMS je přetížený. Filament je pravděpodobně zamotaný nebo zaseklý.',
        '07000200_00010001' => 'Chyba rychlosti a délky filamentu AMS. Odometr filamentu může být vadný.',
        '07001000_00010001' => 'Motor slotu 1 AMS prokluzuje. Podávací kolečko je vadné, nebo je filament příliš tenký.',
        '07001000_00010003' => 'Řízení momentu motoru slotu 1 AMS je vadné. Snímač proudu může být vadný.',
        '07001000_00020002' => 'Motor slotu 1 AMS je přetížený. Filament je pravděpodobně zamotaný nebo zaseklý.',
        '07002000_00020001' => 'AMS Slot 1: došel filament.',
        '07002000_00020002' => 'AMS Slot 1 je prázdný.',
        '07002000_00020003' => 'AMS Slot 1: filament může být přetržený uvnitř AMS.',
        '07002000_00020004' => 'AMS Slot 1: filament může být přetržený v tiskové hlavě.',
        '07002000_00020005' => 'AMS Slot 1: došel filament a čištění starého filamentu proběhlo abnormálně. Zkontrolujte, zda filament neuvízl v tiskové hlavě.',
        '07002000_00020006' => 'PTFE trubička se odpojila během podávání. Zkontrolujte spojení AMS–extrudér.',
        '07002000_00020007' => 'AMS Slot 1: Hallův senzor na výstupu je odpojený. Zkontrolujte konektor.',
        '07002000_00020008' => 'AMS Slot 1: Hallův senzor na vstupu je odpojený. Zkontrolujte konektor.',
        '07002000_00020009' => 'Extruze filamentu AMS Slot 1 selhala. Extrudér je ucpaný, nebo je filament příliš tenký a extrudér prokluzuje.',
        '07002000_0002000A' => 'Nepodařilo se upravit pozici filamentu AMS Slot 1. Filament nebo buffer může být zaseklý.',
        '07002000_00030001' => 'AMS Slot 1: došel filament. Probíhá čištění starého filamentu.',
        '07002000_00030002' => 'AMS Slot 1: došel filament, automaticky přepnuto na slot se stejným materiálem.',
        '07003000_00010001' => 'Chyba RFID desky AMS.',
        '07003500_00010002' => 'Senzor vlhkosti AMS je odpojený. Zkontrolujte konektor.',
        '07004000_00020001' => 'Signál bufferu filamentu ztracen. Kabel nebo poziční senzor může být vadný.',
        '07004000_00020002' => 'Chyba polohového signálu bufferu filamentu. Poziční senzor může být vadný.',
        '07004000_00020003' => 'Komunikace s AMS Hubem je abnormální. Kabel není dobře připojený.',
        '07004000_00020004' => 'Signál bufferu filamentu je abnormální. Pružina může být zaseklá, nebo je filament zamotaný.',
        '07004500_00020001' => 'Senzor řezačky filamentu je nefunkční. Senzor je odpojený nebo vadný.',
        '07004500_00020002' => 'Vzdálenost řezu filamentu je příliš velká. Motor XY zřejmě ztrácí kroky.',
        '07004500_00020003' => 'Rukojeť řezačky filamentu nebyla uvolněna. Rukojeť/čepel je zaseknutá, nebo je problém se senzorem.',
        '07005000_00020001' => 'Komunikace AMS je abnormální. Zkontrolujte připojovací kabel.',
        '07005100_00030001' => 'AMS je vypnutý. Zaveďte filament z držáku cívky.',
        '07005500_00010002' => 'Nesprávné pořadí PTFE trubiček mezi tiskovou hlavou a bufferem.',
        '07005500_00020001' => 'Detekován nový AMS. Nastavte, ke kterému extrudéru je AMS připojen.',
        '07005500_00010004' => 'Propojení AMS s extrudérem je nesprávné. Spusťte nastavení AMS.',
        '07006000_00020001' => 'AMS Slot 1 je přetížený. Filament je zamotaný, nebo je cívka zaseklá.',
        '07007000_00020001' => 'Nepodařilo se vytáhnout filament z extrudéru. Zkontrolujte ucpání extrudéru nebo přetržení filamentu uvnitř.',
        '07007000_00020002' => 'Nepodařilo se zavést filament do tiskové hlavy. Zkontrolujte, zda filament nebo cívka neuvízly.',
        '07007000_00020003' => 'Nepodařilo se vytlačit filament. Zkontrolujte ucpání extrudéru nebo trysky.',
        '07007000_00020004' => 'Nepodařilo se stáhnout filament z tiskové hlavy zpět do AMS. Zkontrolujte, zda filament nebo cívka neuvízly.',
        '07007000_00020005' => 'Nepodařilo se zavést filament mimo AMS. Zastřihněte konec filamentu a zkontrolujte cívku.',
        '07007000_00020006' => 'Vypršel časový limit čištění starého filamentu. Zkontrolujte ucpání extrudéru/trysky.',
        '07007000_00020007' => 'Došel filament v AMS. Vložte nový a klikněte na "Zkusit znovu".',
        '07007000_00020008' => 'Nepodařilo se načíst mapovací tabulku AMS. Klikněte na "Pokračovat".',

        // ---- 07FE / 07FF: externí cívka bez AMS (P1/A1 sérii) ----
        '07FE0000_00020001' => 'Externí filament levého extruderu došel. Založte nový filament.',
        '07FE0000_00020002' => 'V extrudéru nebyl detekován filament z externí cívky. Založte nový filament.',
        '07FE0000_00020004' => 'Vytáhněte externí filament z levého extruderu.',
        '07FF0000_00020001' => 'Externí filament došel. Založte nový filament.',
        '07FF0000_00020002' => 'Externí filament chybí. Založte nový filament.',
        '07FF0000_00020004' => 'Vytáhněte filament z externí cívky z extruderu.',
        '07FE8000_00010001' => 'Motor zvedání tiskové hlavy pracuje abnormálně. Zkontrolujte, zda kabel není uvolněný.',
        '07FE8000_00010002' => 'Hallův senzor polohy motoru zvedání má otevřený obvod. Zkontrolujte kabel.',
        '07FE8000_00020001' => 'Zvedací pohyb při přepínání extruderu je abnormální. Zkontrolujte, zda není zaseklý blokátor toku nebo filament v hlavě.',
        '07FE8100_00010001' => 'Motor přepínání extruderu pracuje abnormálně. Zkontrolujte, zda kabel není uvolněný.',
        '07FE8100_00020001' => 'Přepínání extruderu je abnormální. Zkontrolujte, zda v tiskové hlavě není něco zaseklé.',
        '07FEA000_00020001' => 'Vypršel časový limit "cold pull" levé trysky. Klikněte na "Zkusit znovu" a filament vytáhněte ručně.',

        // ---- 1200 / 12FF: AMS Lite (A1 série) ----
        '1200FF00_00020007' => 'Nepodařilo se zjistit polohu filamentu v tiskové hlavě. Klikněte pro další nápovědu.',

        // ---- 18: AMS-HT – vysokoteplotní AMS (H2D) ----
        '18002400_00010007' => 'Detekce dveří AMS-HT je abnormální, Hallův senzor může mít uvolněné nebo přerušené připojení.',
        '18002400_00020009' => 'Přední kryt AMS-HT je otevřený. Může to ovlivnit sušení nebo způsobit navlhnutí filamentu.',
    ];
}
