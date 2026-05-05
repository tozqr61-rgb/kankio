<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameParticipant extends Model
{
    protected $fillable = [
        'game_session_id',
        'user_id',
        'joined_at',
        'left_at',
        'is_active',
        'total_score',
        'is_ready',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'is_active' => 'boolean',
            'is_ready' => 'boolean',
        ];
    }

    public function gameSession()
    {
        return $this->belongsTo(GameSession::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
