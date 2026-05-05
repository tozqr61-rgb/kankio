<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('game_type')->default('isim_sehir');
            $table->string('status')->default('waiting');
            $table->unsignedInteger('current_round_no')->default(0);
            $table->unsignedInteger('max_players')->nullable();
            $table->unsignedInteger('round_time_seconds')->default(60);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'status']);
        });

        Schema::create('game_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('total_score')->default(0);
            $table->boolean('is_ready')->default(false);
            $table->timestamps();

            $table->unique(['game_session_id', 'user_id']);
            $table->index(['game_session_id', 'is_active']);
        });

        Schema::create('game_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('round_no');
            $table->string('letter', 2);
            $table->string('status')->default('collecting');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submission_deadline')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('results_published_at')->nullable();
            $table->timestamps();

            $table->unique(['game_session_id', 'round_no']);
            $table->index(['game_session_id', 'status']);
        });

        Schema::create('game_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('answers')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->integer('score_total')->default(0);
            $table->json('score_breakdown')->nullable();
            $table->timestamps();

            $table->unique(['game_round_id', 'user_id']);
            $table->index(['game_round_id', 'is_locked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_submissions');
        Schema::dropIfExists('game_rounds');
        Schema::dropIfExists('game_participants');
        Schema::dropIfExists('game_sessions');
    }
};
