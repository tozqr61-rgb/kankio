<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameSession extends Model
{
    protected $fillable = [
        'room_id',
        'created_by',
        'game_type',
        'status',
        'current_round_no',
        'max_players',
        'round_time_seconds',
        'started_at',
        'ended_at',
        'last_activity_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants()
    {
        return $this->hasMany(GameParticipant::class);
    }

    public function rounds()
    {
        return $this->hasMany(GameRound::class);
    }

    public function currentRound()
    {
        return $this->hasOne(GameRound::class)->whereColumn('round_no', 'game_sessions.current_round_no');
    }
}
