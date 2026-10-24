<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            // Poslední mesh podložky, který má tiskárna v paměti:
            // {"valid": bool, "measured_at": ISO8601|null, "bed_type": string|null,
            //  "bed_temp": int|null, "reason": string|null}
            $table->json('bed_mesh')->nullable()->after('pending_ams_kick');
        });

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->string('bed_type')->nullable()->after('plate_index');
            $table->unsignedSmallInteger('bed_temp')->nullable()->after('bed_type');
            $table->boolean('bed_leveled')->nullable()->after('bed_temp');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropColumn(['bed_type', 'bed_temp', 'bed_leveled']);
        });

        Schema::table('printers', function (Blueprint $table) {
            $table->dropColumn('bed_mesh');
        });
    }
};
