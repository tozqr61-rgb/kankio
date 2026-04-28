<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_music_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('video_id')->nullable();
            $table->string('video_title')->nullable();
            $table->boolean('is_playing')->default(false);
            $table->decimal('position', 10, 3)->default(0); // seconds when state was last saved
            $table->json('queue')->nullable();              // [{"video_id":"...", "title":"..."}, ...]
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('state_updated_at')->nullable(); // when play/pause/seek happened
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_music_states');
    }
};
