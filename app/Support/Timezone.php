<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Časová zóna pro ZOBRAZENÍ časů (appka, notifikace).
 *
 * Databáze i aplikace dál pracují v UTC - časy se převádějí až při výpisu
 * přes ->local() (makro na Carbonu, viz AppServiceProvider). Díky tomu se
 * letní/zimní čas řeší sám a změna zóny platí i pro starou historii.
 */
class Timezone
{
    public const SETTING = 'timezone';
    private const CACHE  = 'bambups:timezone';

    private static ?string $memo = null;
    private static int $memoAt   = 0;

    public static function get(): string
    {
        // Daemony běží dlouho - změnu z nastavení převezmou do minuty
        if (self::$memo !== null && time() - self::$memoAt < 60) {
            return self::$memo;
        }

        try {
            $tz = Cache::remember(self::CACHE, 300, fn () => AppSetting::get(self::SETTING));
        } catch (\Throwable) {
            $tz = null; // např. před migrací
        }
        if (!$tz || !self::isValid($tz)) {
            $tz = config('bambups.timezone', 'Europe/Prague');
        }
        if (!self::isValid($tz)) {
            $tz = 'UTC';
        }

        self::$memoAt = time();
        return self::$memo = $tz;
    }

    public static function set(string $tz): void
    {
        AppSetting::set(self::SETTING, $tz);
        Cache::forget(self::CACHE);
        self::$memo = null;
    }

    public static function isValid(string $tz): bool
    {
        return in_array($tz, \DateTimeZone::listIdentifiers(), true);
    }
}
