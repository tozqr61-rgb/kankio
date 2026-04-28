<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomMusicState extends Model
{
    protected $fillable = [
        'room_id', 'video_id', 'video_title', 'is_playing',
        'position', 'video_duration', 'queue', 'updated_by', 'state_updated_at', 'started_at_unix',
    ];

    protected function casts(): array
    {
        return [
            'is_playing'       => 'boolean',
            'position'         => 'float',
            'video_duration'   => 'float',
            'started_at_unix'  => 'integer',
            'queue'            => 'array',
            'state_updated_at' => 'datetime',
        ];
    }

    /**
     * Compute current playback position.
     * Formula mirrors the client: floor(now - started_at_unix)
     * → all clients calculate the same value deterministically.
     */
    public function getCurrentPositionAttribute(): float
    {
        if (! $this->is_playing || ! $this->started_at_unix) {
            return (float) $this->position;
        }
        return max(0.0, (float) (time() - $this->started_at_unix));
    }
}
