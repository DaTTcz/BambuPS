<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            // Vlastní (externí) kamera místo vestavěné - např. mjpg-streamer na RPi.
            // Prázdné = Bambu kamera přes rtsps://…:322
            $table->string('camera_stream_url', 500)->nullable()->after('access_code');
            $table->string('camera_snapshot_url', 500)->nullable()->after('camera_stream_url');
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropColumn(['camera_stream_url', 'camera_snapshot_url']);
        });
    }
};
