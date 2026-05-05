<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'room_id',
        'sender_id',
        'content',
        'title',
        'audio_url',
        'audio_duration',
        'reply_to',
        'is_edited',
        'is_system_message',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_edited'         => 'boolean',
            'is_system_message' => 'boolean',
        ];
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function replyToMessage()
    {
        return $this->belongsTo(Message::class, 'reply_to')->with('sender');
    }

    public function reads()
    {
        return $this->belongsToMany(User::class, 'message_reads')
            ->withPivot('read_at');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
