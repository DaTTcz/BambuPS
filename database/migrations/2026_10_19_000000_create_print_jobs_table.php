<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();

            // Vazby jsou volitelné a při smazání tiskárny/souboru se jen
            // vynulují - historie má zůstat i po úklidu souborů/tiskáren.
            $table->foreignId('printer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot jmen pro případ, že tiskárna/soubor zmizí
            $table->string('printer_name')->nullable();
            $table->string('name');                         // subtask_name / název souboru
            $table->unsignedSmallInteger('plate_index')->nullable();

            // bambups = spuštěno z appky, external = Bambu Studio / displej / Handy
            $table->string('source', 16)->default('external');
            $table->string('task_id', 32)->nullable();

            // queued | running | finished | failed | cancelled
            $table->string('status', 16)->default('running');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('estimated_seconds')->nullable();

            $table->unsignedTinyInteger('progress')->nullable();     // % při konci
            $table->unsignedInteger('layer_reached')->nullable();
            $table->unsignedInteger('total_layers')->nullable();

            // Odhad ze slicéru pro celý tisk; u nedokončených se v UI přepočítá podle progress
            $table->decimal('filament_g', 10, 2)->nullable();
            $table->json('filaments')->nullable();                   // [{type, color, used_g}]

            $table->unsignedBigInteger('print_error')->nullable();
            $table->json('hms')->nullable();
            $table->string('snapshot_path')->nullable();

            $table->timestamps();

            $table->index(['printer_id', 'status']);
            $table->index('started_at');
            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
