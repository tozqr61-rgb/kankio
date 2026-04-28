<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MusicStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int   $roomId,
        public readonly array $state,
    ) {}

    /** Broadcast on the room's music channel */
    public function broadcastOn(): array
    {
        return [new Channel("room.{$this->roomId}.music")];
    }

    public function broadcastAs(): string
    {
        return 'music.state';
    }

    public function broadcastWith(): array
    {
        return $this->state;
    }
}
