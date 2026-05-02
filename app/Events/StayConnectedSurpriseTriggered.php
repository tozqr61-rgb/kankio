<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StayConnectedSurpriseTriggered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $roomId,
        public readonly array $triggeredBy,
        public readonly int $userId,
        public readonly string $triggerId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'stay.connected';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->triggerId,
            'room_id' => $this->roomId,
            'triggered_by' => $this->triggeredBy,
        ];
    }
}
