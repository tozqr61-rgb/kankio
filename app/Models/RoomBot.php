<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomBot extends Model
{
    protected $fillable = [
        'room_id',
        'bot_id',
        'is_enabled',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'settings'   => 'array',
        ];
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }
}
