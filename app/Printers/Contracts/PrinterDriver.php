<?php

namespace App\Printers\Contracts;

use App\Models\Printer;
use App\Printers\PrintRequest;

/**
 * Ovladač tiskárny - jediné místo, kde appka ví, JAK s konkrétním typem
 * tiskárny mluvit (Bambu MQTT/FTPS, PrusaLink HTTP, Moonraker...).
 *
 * Zbytek appky (dashboard, detail, dialog tisku, historie, notifikace)
 * pracuje jen s tímto rozhraním a se společným tvarem stavu tiskárny
 * (viz docs/PRINTER_DRIVERS.md).
 *
 * Nový ovladač: odděď App\Printers\AbstractPrinterDriver, implementuj, co
 * tiskárna umí, a zaregistruj třídu v config/printers.php.
 */
interface PrinterDriver
{
    /** Krátký jedinečný klíč, ukládá se do printers.driver (např. "bambu") */
    public static function key(): string;

    /** Název pro uživatele, např. "Bambu Lab (LAN)" */
    public static function label(): string;

    /** Jedna věta pro výběr typu tiskárny */
    public static function description(): string;

    /**
     * Pole formuláře pro připojení tiskárny. Formulář v Nastavení → Tiskárny
     * se z nich sestaví sám, ovladač nemusí mít vlastní UI.
     *
     * @return array<string, array{label: string, type: string, rules: string, help?: string, placeholder?: string, secret?: bool}>
     *         klíč = název pole v printers.connection (nebo sloupec printers.*)
     */
    public static function connectionFields(): array;

    /** @return string[] konstanty z App\Printers\Capability */
    public static function capabilities(): array;

    /** Přípony souborů, které tiskárna umí tisknout, např. ['3mf'] */
    public static function fileTypes(): array;

    public function __construct(Printer $printer);

    public function supports(string $capability): bool;

    /** Ověření připojení (pro tlačítko "Otestovat" ve formuláři) */
    public function testConnection(): array; // ['ok' => bool, 'message' => string]

    // ---- Soubory a tisk -------------------------------------------------

    /** Nahraje soubor do tiskárny; true = OK */
    public function uploadFile(string $localPath, string $remoteName): bool;

    /** Spustí tisk nahraného souboru; vrací ID úlohy (pro historii) nebo null při chybě */
    public function startPrint(PrintRequest $request): ?string;

    public function pause(): bool;
    public function resume(): bool;
    public function stop(): bool;

    // ---- Ovládání (vrací false, když tiskárna neumí) --------------------

    public function setHotendTemp(int $temp, int $tool = 0): bool;
    public function setBedTemp(int $temp): bool;
    /** @param int $fan index ventilátoru podle ovladače, $percent 0-100 */
    public function setFan(int $fan, int $percent): bool;
    public function setPrintSpeed(int $percent): bool;
    public function setLight(string $node, string $mode): bool;
    public function homeAll(): bool;
    public function homeAxis(string $axis): bool;
    public function moveAxis(string $axis, float $distance, int $speed = 3000): bool;
    public function sendGcode(string $gcode): bool;

    // ---- Kamera ---------------------------------------------------------

    /** Zdroj streamu vestavěné kamery pro go2rtc (rtsp://, http://...), nebo null */
    public function cameraSource(): ?string;
}
