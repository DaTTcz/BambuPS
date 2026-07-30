<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            // Naplánovaný AMS "kick" příkaz, co čeká, až tryska dosáhne
            // cílové teploty. Appka ho vytvoří po startu tisku a MQTT
            // listener ho odešle, jakmile teplota sedí, místo slepého čekání.
            // Obsah: {"ams_id": int, "slot_id": int, "requested_at": "ISO8601"}
            $table->json('pending_ams_kick')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropColumn('pending_ams_kick');
        });
    }
};
