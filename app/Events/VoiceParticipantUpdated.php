<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoiceParticipantUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $roomId,
        public readonly array $participant,
        public readonly string $action = 'updated',
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("room.{$this->roomId}.voice")];
    }

    public function broadcastAs(): string
    {
        return 'voice.participant';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'participant' => $this->participant,
        ];
    }
}
