<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts the current voice participant list to everyone in the room
 * whenever someone joins, leaves, or toggles mute.
 */
class VoiceStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int   $roomId,
        public readonly array $participants,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("room.{$this->roomId}.voice")];
    }

    public function broadcastAs(): string
    {
        return 'voice.state';
    }

    public function broadcastWith(): array
    {
        return ['participants' => $this->participants];
    }
}
