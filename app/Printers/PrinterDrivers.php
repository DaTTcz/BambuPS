<?php

namespace App\Printers;

use App\Models\Printer;
use App\Printers\Contracts\PrinterDriver;

/**
 * Seznam dostupných ovladačů tiskáren.
 *
 * Ovladače se berou z config/printers.php. Balíček nebo service provider
 * může přidat další přes PrinterDrivers::register(MujOvladac::class).
 */
final class PrinterDrivers
{
    /** @var array<string, class-string<PrinterDriver>> */
    private static array $extra = [];

    public static function register(string $class): void
    {
        if (!is_subclass_of($class, PrinterDriver::class)) {
            throw new \InvalidArgumentException("{$class} neimplementuje " . PrinterDriver::class);
        }
        self::$extra[$class::key()] = $class;
    }

    /** @return array<string, class-string<PrinterDriver>> klíč => třída */
    public static function all(): array
    {
        $out = [];
        foreach ((array) config('printers.drivers', []) as $class) {
            if (is_subclass_of($class, PrinterDriver::class)) {
                $out[$class::key()] = $class;
            }
        }
        return array_merge($out, self::$extra);
    }

    /** @return class-string<PrinterDriver> */
    public static function classFor(string $key): string
    {
        $all = self::all();
        if (!isset($all[$key])) {
            throw new \RuntimeException("Neznámý typ tiskárny \"{$key}\" - ovladač není nainstalovaný.");
        }
        return $all[$key];
    }

    public static function make(Printer $printer): PrinterDriver
    {
        $class = self::classFor($printer->driver ?: 'bambu');
        return new $class($printer);
    }
}
