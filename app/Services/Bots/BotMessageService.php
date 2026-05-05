<?php

namespace App\Services\Bots;

use App\Events\MessageSent;
use App\Models\Bot;
use App\Models\Message;
use App\Models\Room;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BotMessageService
{
    public function send(Room $room, Bot $bot, string $content, array $botData = []): ?Message
    {
        $dedupeKey = 'bot_msg_' . md5($bot->bot_key . $room->id . $content);

        if (Cache::has($dedupeKey)) {
            return null;
        }

        Cache::put($dedupeKey, true, 3);

        $message = Message::create([
            'room_id'           => $room->id,
            'sender_id'         => $bot->user_id,
            'content'           => $content,
            'is_system_message' => true,
        ]);

        $message->load('sender');

        $payload = $this->formatPayload($message, $botData);

        try {
            broadcast(new MessageSent($room->id, $payload));
        } catch (\Throwable $e) {
            Log::warning('bot.broadcast.failed', [
                'bot'     => $bot->bot_key,
                'room_id' => $room->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return $message;
    }

    private function formatPayload(Message $message, array $botData = []): array
    {
        $payload = [
            'id'                => $message->id,
            'room_id'           => $message->room_id,
            'content'           => $message->content,
            'is_system_message' => true,
            'created_at'        => $message->created_at?->toISOString(),
            'sender'            => [
                'id'       => $message->sender?->id,
                'username' => $message->sender?->username,
                'is_bot'   => true,
            ],
        ];

        if (! empty($botData)) {
            $payload['bot_data'] = $botData;
        }

        return $payload;
    }
}
