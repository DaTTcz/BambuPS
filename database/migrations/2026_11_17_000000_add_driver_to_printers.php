<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            // Typ tiskárny = klíč ovladače (App\Printers\PrinterDrivers)
            $table->string('driver', 40)->default('bambu')->after('id');
            // Nastavení připojení pro ovladače bez vlastních sloupců
            // (API klíč PrusaLink, adresa Moonrakeru...). Šifrované.
            $table->text('connection')->nullable()->after('access_code');
        });

        // Sériové číslo a přístupový kód má jen Bambu
        Schema::table('printers', function (Blueprint $table) {
            $table->string('serial_number')->nullable()->change();
            $table->string('access_code')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropColumn(['driver', 'connection']);
        });
    }
};
