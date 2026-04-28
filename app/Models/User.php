<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'avatar_url',
        'role',
        'is_banned',
        'notifications_enabled',
        'last_seen_at',
        'last_avatar_update',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'              => 'hashed',
            'is_banned'             => 'boolean',
            'notifications_enabled' => 'boolean',
            'last_seen_at'          => 'datetime',
            'last_avatar_update'    => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'room_members', 'user_id', 'room_id')
            ->withPivot('role');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }
}
