<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameSubmission extends Model
{
    protected $fillable = [
        'game_round_id',
        'user_id',
        'answers',
        'submitted_at',
        'is_locked',
        'score_total',
        'score_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'submitted_at' => 'datetime',
            'is_locked' => 'boolean',
            'score_breakdown' => 'array',
        ];
    }

    public function round()
    {
        return $this->belongsTo(GameRound::class, 'game_round_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
