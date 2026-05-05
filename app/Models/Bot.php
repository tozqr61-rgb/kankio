<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bot extends Model
{
    protected $fillable = [
        'user_id',
        'bot_key',
        'display_name',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings'  => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'room_bots')
            ->withPivot(['is_enabled', 'settings'])
            ->withTimestamps();
    }

    public function roomBots()
    {
        return $this->hasMany(RoomBot::class);
    }

    public static function findByKey(string $key): ?self
    {
        return static::where('bot_key', $key)->first();
    }
}
