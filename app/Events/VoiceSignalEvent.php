<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Delivered instantly to the target user's private WebSocket channel.
 * Replaces HTTP polling for WebRTC offer/answer/ice signaling.
 */
class VoiceSignalEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $toUserId,
        public readonly int    $fromUserId,
        public readonly string $type,
        public readonly string $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("voice.signal.{$this->toUserId}")];
    }

    public function broadcastAs(): string
    {
        return 'voice.signal';
    }

    public function broadcastWith(): array
    {
        return [
            'from_user_id' => $this->fromUserId,
            'type'         => $this->type,
            'payload'      => $this->payload,
        ];
    }
}
