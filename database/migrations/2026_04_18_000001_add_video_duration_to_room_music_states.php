<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_music_states', function (Blueprint $table) {
            $table->decimal('video_duration', 10, 3)->default(0)->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('room_music_states', function (Blueprint $table) {
            $table->dropColumn('video_duration');
        });
    }
};
