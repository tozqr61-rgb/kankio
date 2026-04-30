<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
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
        public readonly int $roomId,
        public readonly array $participants,
        public readonly ?array $settings = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("room.{$this->roomId}.voice")];
    }

    public function broadcastAs(): string
    {
        return 'voice.state';
    }

    public function broadcastWith(): array
    {
        return array_filter([
            'participants' => $this->participants,
            'settings' => $this->settings,
        ], fn ($value) => $value !== null);
    }
}
