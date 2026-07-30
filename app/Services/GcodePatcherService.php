<?php

namespace App\Services;

class GcodePatcherService
{
    /**
     * Vytvoří dočasnou kopii .3mf souboru s přepsanou teplotou podložky
     * v EXEKUČNÍM gcode (ne jen v metadatech/komentářích) - pro tisk na
     * jinou fyzickou podložku, než pro jakou byl soubor původně
     * naslicovaný. Originál na disku zůstává netknutý.
     *
     * Bambu firmware řídí teplotu podložky přes M140/M190 s natvrdo
     * dosazeným číslem (placeholder [bed_temperature_initial_layer_single]
     * se nahradí konkrétní hodnotou už při slicování, ne za běhu tisku -
     * proto MQTT "bed_type" pole samo o sobě teplotu nezmění).
     *
     * @return string Cesta k dočasnému patchnutému souboru (smaž po použití!)
     * @throws \RuntimeException Pokud se soubor nepodaří otevřít/upravit
     */
    public function patchBedTemp(string $sourcePath, int $plateIndex, int $newTemp): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'bambups_patch_') . '.3mf';

        if (!copy($sourcePath, $tempPath)) {
            throw new \RuntimeException('Nepodařilo se vytvořit dočasnou kopii souboru.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tempPath) !== true) {
            @unlink($tempPath);
            throw new \RuntimeException('Nepodařilo se otevřít 3mf pro úpravu teploty podložky.');
        }

        $gcodeEntry = "Metadata/plate_{$plateIndex}.gcode";
        $content    = $zip->getFromName($gcodeEntry);

        if ($content === false) {
            $zip->close();
            @unlink($tempPath);
            throw new \RuntimeException("Soubor {$gcodeEntry} nebyl v 3mf nalezen.");
        }

        // Přepíšeme podle KOMENTÁŘE za příkazem (";set bed temp" /
        // ";wait for bed temp"), ne jen podle "M140"/"M190" - ať omylem
        // nezasáhneme jiný výskyt, např. "M140 S0 ; turn off bed" na
        // konci souboru. Limit 1 = jen první (jediný) výskyt.
        $content = preg_replace(
            '/M140 S\d+(\.\d+)? ;set bed temp/',
            "M140 S{$newTemp} ;set bed temp",
            $content,
            1,
            $count1
        );
        $content = preg_replace(
            '/M190 S\d+(\.\d+)? ;wait for bed temp/',
            "M190 S{$newTemp} ;wait for bed temp",
            $content,
            1,
            $count2
        );

        if ($count1 === 0 || $count2 === 0) {
            $zip->close();
            @unlink($tempPath);
            throw new \RuntimeException('V gcode nebyl nalezen očekávaný řádek pro nastavení teploty podložky - soubor nebyl upraven.');
        }

        $zip->addFromString($gcodeEntry, $content);
        $zip->close();

        return $tempPath;
    }
}
