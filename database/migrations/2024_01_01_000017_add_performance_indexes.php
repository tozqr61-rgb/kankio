<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* Composite index for message polling: WHERE room_id=? AND created_at>? */
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['room_id', 'created_at'], 'messages_room_created_idx');
        });

        /* Composite unique key for room_reads: WHERE user_id=? AND room_id=? */
        Schema::table('room_reads', function (Blueprint $table) {
            $table->unique(['user_id', 'room_id'], 'room_reads_user_room_unique');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_room_created_idx');
        });

        Schema::table('room_reads', function (Blueprint $table) {
            $table->dropUnique('room_reads_user_room_unique');
        });
    }
};
