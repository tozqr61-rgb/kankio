<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameSessionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $roomId,
        public readonly int $gameSessionId,
        public readonly string $type,
        public readonly array $state,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("room.{$this->roomId}.game")];
    }

    public function broadcastAs(): string
    {
        return 'game.session';
    }

    public function broadcastWith(): array
    {
        return [
            'game_session_id' => $this->gameSessionId,
            'type' => $this->type,
            'state' => $this->state,
        ];
    }
}
