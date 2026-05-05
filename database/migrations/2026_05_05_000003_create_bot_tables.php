<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_bot')->default(false)->after('presence_mode');
        });

        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bot_key', 64)->unique();
            $table->string('display_name', 80);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('room_bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bot_id')->constrained('bots')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['room_id', 'bot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_bots');
        Schema::dropIfExists('bots');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_bot');
        });
    }
};
