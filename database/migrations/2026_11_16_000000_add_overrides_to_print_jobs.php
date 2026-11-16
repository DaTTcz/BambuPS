<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            // Co se před tiskem změnilo oproti souboru: ["Podložka: ... → ...", "Tryska: ..."]
            $table->json('overrides')->nullable()->after('filaments');
            // Volby tisku: {bed_leveling, timelapse, flow_cali, layer_inspect, vibration_cali, use_ams}
            $table->json('options')->nullable()->after('overrides');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropColumn(['overrides', 'options']);
        });
    }
};
