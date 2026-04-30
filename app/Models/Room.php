<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = [
        'name',
        'type',
        'created_by',
        'is_archived',
        'voice_members_only',
        'voice_requires_permission',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'voice_members_only' => 'boolean',
            'voice_requires_permission' => 'boolean',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'room_members', 'room_id', 'user_id')
            ->withPivot('role');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
