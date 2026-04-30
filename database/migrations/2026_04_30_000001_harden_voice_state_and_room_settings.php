<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voice_sessions', function (Blueprint $table) {
            $table->boolean('is_deafened')->default(false)->after('is_muted');
            $table->boolean('is_speaking')->default(false)->after('is_deafened');
            $table->boolean('can_speak')->default(true)->after('is_speaking');
            $table->string('connection_quality', 16)->default('unknown')->after('can_speak');
            $table->unsignedInteger('reconnect_count')->default(0)->after('connection_quality');
            $table->timestamp('last_client_event_at')->nullable()->after('last_ping');
            $table->index(['room_id', 'is_active', 'last_ping'], 'voice_sessions_room_active_ping_idx');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->boolean('voice_members_only')->default(false)->after('is_archived');
            $table->boolean('voice_requires_permission')->default(false)->after('voice_members_only');
        });
    }

    public function down(): void
    {
        Schema::table('voice_sessions', function (Blueprint $table) {
            $table->dropIndex('voice_sessions_room_active_ping_idx');
            $table->dropColumn([
                'is_deafened',
                'is_speaking',
                'can_speak',
                'connection_quality',
                'reconnect_count',
                'last_client_event_at',
            ]);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['voice_members_only', 'voice_requires_permission']);
        });
    }
};
