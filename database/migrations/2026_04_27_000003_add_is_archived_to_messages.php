<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->after('is_system_message');
            $table->index(['room_id', 'is_archived', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['room_id', 'is_archived', 'created_at']);
            $table->dropColumn('is_archived');
        });
    }
};
