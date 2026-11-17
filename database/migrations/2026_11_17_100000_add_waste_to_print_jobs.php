<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            // Odpad u vícebarevného tisku (proplach + čistící věž), odhad ze souboru
            $table->decimal('waste_g', 8, 2)->nullable()->after('filament_g');
            $table->unsignedInteger('filament_changes')->nullable()->after('waste_g');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropColumn(['waste_g', 'filament_changes']);
        });
    }
};
