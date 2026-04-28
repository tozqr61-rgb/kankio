<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_music_states', function (Blueprint $table) {
            /* Unix timestamp (seconds) when the current track started/resumed.
             * Clients calculate: current_position = floor(now() - started_at_unix)
             * Perfect sync — all clients use the same formula, same result. */
            $table->bigInteger('started_at_unix')->nullable()->after('state_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('room_music_states', function (Blueprint $table) {
            $table->dropColumn('started_at_unix');
        });
    }
};
