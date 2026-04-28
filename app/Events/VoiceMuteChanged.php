<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Lightweight mute-toggle event.
 * Broadcasts only {user_id, is_muted} instead of the full participants list,
 * reducing Reverb payload by ~95% on every mute toggle.
 */
class VoiceMuteChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int  $roomId,
        public readonly int  $userId,
        public readonly bool $isMuted,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("room.{$this->roomId}.voice")];
    }

    public function broadcastAs(): string
    {
        return 'voice.mute';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'  => $this->userId,
            'is_muted' => $this->isMuted,
        ];
    }
}
