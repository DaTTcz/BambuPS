<?php

namespace App\Printers;

use App\Models\Printer;
use App\Printers\Contracts\PrinterDriver;

/**
 * Základ pro ovladače: všechno, co tiskárna neumí, vrací false / null.
 * Ovladač přepíše jen metody, které jeho tiskárna podporuje.
 */
abstract class AbstractPrinterDriver implements PrinterDriver
{
    public function __construct(protected Printer $printer) {}

    public static function description(): string
    {
        return '';
    }

    public static function connectionFields(): array
    {
        return [];
    }

    public static function capabilities(): array
    {
        return [];
    }

    public static function fileTypes(): array
    {
        return ['gcode'];
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, static::capabilities(), true);
    }

    /** Hodnota z nastavení připojení (printers.connection), případně ze sloupce tiskárny */
    protected function conn(string $key, mixed $default = null): mixed
    {
        return $this->printer->connection[$key] ?? $this->printer->getAttribute($key) ?? $default;
    }

    public function testConnection(): array
    {
        return ['ok' => false, 'message' => 'Test připojení není pro tento typ tiskárny k dispozici.'];
    }

    public function uploadFile(string $localPath, string $remoteName): bool { return false; }
    public function startPrint(PrintRequest $request): ?string { return null; }
    public function pause(): bool { return false; }
    public function resume(): bool { return false; }
    public function stop(): bool { return false; }

    public function setHotendTemp(int $temp, int $tool = 0): bool { return false; }
    public function setBedTemp(int $temp): bool { return false; }
    public function setFan(int $fan, int $percent): bool { return false; }
    public function setPrintSpeed(int $percent): bool { return false; }
    public function setLight(string $node, string $mode): bool { return false; }
    public function homeAll(): bool { return false; }
    public function homeAxis(string $axis): bool { return false; }
    public function moveAxis(string $axis, float $distance, int $speed = 3000): bool { return false; }
    public function sendGcode(string $gcode): bool { return false; }

    public function cameraSource(): ?string { return null; }
}
