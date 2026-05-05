<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameRound extends Model
{
    protected $fillable = [
        'game_session_id',
        'round_no',
        'letter',
        'status',
        'started_at',
        'submission_deadline',
        'ended_at',
        'results_published_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submission_deadline' => 'datetime',
            'ended_at' => 'datetime',
            'results_published_at' => 'datetime',
        ];
    }

    public function gameSession()
    {
        return $this->belongsTo(GameSession::class);
    }

    public function submissions()
    {
        return $this->hasMany(GameSubmission::class);
    }
}
