<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('audio_url')->nullable()->after('content');
            $table->unsignedSmallInteger('audio_duration')->nullable()->after('audio_url');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['audio_url', 'audio_duration']);
        });
    }
};
