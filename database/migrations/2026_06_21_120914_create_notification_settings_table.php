<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('channel'); // email, telegram, mqtt
            $table->boolean('enabled')->default(false);
            $table->json('config')->nullable(); // channel-specific config
            $table->boolean('on_print_done')->default(true);
            $table->boolean('on_print_failed')->default(true);
            $table->boolean('on_hms_error')->default(true);
            $table->boolean('on_filament_runout')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
