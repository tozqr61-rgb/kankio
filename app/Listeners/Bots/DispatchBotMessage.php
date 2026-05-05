<?php

namespace App\Listeners\Bots;

use App\Events\MessageSent;
use App\Models\Room;
use App\Models\User;
use App\Services\Bots\BotManager;
use Illuminate\Support\Facades\Log;

class DispatchBotMessage
{
    public function __construct(private BotManager $botManager)
    {
    }

    public function handle(MessageSent $event): void
    {
        $content  = $event->message['content'] ?? '';
        $senderId = $event->message['sender']['id'] ?? null;

        if (! $this->botManager->isCommand($content)) {
            return;
        }

        if (! $senderId) {
            return;
        }

        $sender = User::find($senderId);
        $room   = Room::find($event->roomId);

        if (! $sender || ! $room) {
            return;
        }

        if ($sender->is_bot) {
            return;
        }

        try {
            $this->botManager->dispatch($content, $room, $sender);
        } catch (\Throwable $e) {
            Log::error('bot.listener.dispatch_failed', [
                'room_id'  => $event->roomId,
                'sender'   => $senderId,
                'content'  => mb_substr($content, 0, 100),
                'error'    => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('bot.listener.job_failed', ['error' => $exception->getMessage()]);
    }
}
