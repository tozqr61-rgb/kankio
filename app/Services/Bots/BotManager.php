<?php

namespace App\Services\Bots;

use App\Models\Room;
use App\Models\Bot;
use App\Models\User;
use App\Services\Bots\Contracts\BotInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BotManager
{
    /** @var BotInterface[] */
    private array $bots = [];

    public function __construct(private BotCommandParser $parser)
    {
    }

    public function register(BotInterface $bot): void
    {
        $this->bots[$bot->botKey()] = $bot;
    }

    /**
     * Called when a message arrives. If it's a slash command, dispatch to the
     * first bot that claims it and return its response. Otherwise return null.
     */
    public function dispatch(string $content, Room $room, User $sender): ?BotResponse
    {
        $ctx = $this->parser->parse($content, $room, $sender);

        if (! $ctx) {
            return null;
        }

        foreach ($this->bots as $bot) {
            if (! $bot->supportsRoom($room)) {
                continue;
            }

            $botModel = Bot::findByKey($bot->botKey());
            if (! $botModel?->is_active || ! $this->roomBotEnabled($botModel, $room)) {
                continue;
            }

            if (! $bot->supportsCommand($ctx->command)) {
                continue;
            }

            $cooldownSeconds = (int) ($botModel->settings['cooldown_seconds'] ?? 3);
            if ($cooldownSeconds > 0 && ! Cache::add($this->commandCooldownKey($bot->botKey(), $ctx), true, now()->addSeconds($cooldownSeconds))) {
                return BotResponse::error('Komut çok hızlı tekrarlandı.');
            }

            try {
                $response = $bot->handleCommand($ctx);
                Log::info('bot.command.handled', [
                    'bot'     => $bot->botKey(),
                    'command' => $ctx->command,
                    'room_id' => $room->id,
                    'user_id' => $sender->id,
                ]);

                return $response;
            } catch (\Throwable $e) {
                Log::error('bot.command.error', [
                    'bot'     => $bot->botKey(),
                    'command' => $ctx->command,
                    'room_id' => $room->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Broadcast a system event to all registered bots that support the room.
     */
    public function broadcastEvent(string $event, array $payload, ?Room $room = null): void
    {
        foreach ($this->bots as $bot) {
            if ($room && ! $bot->supportsRoom($room)) {
                continue;
            }

            $botModel = Bot::findByKey($bot->botKey());
            if (! $botModel?->is_active || ($room && ! $this->roomBotEnabled($botModel, $room))) {
                continue;
            }

            $dedupeKey = $this->eventDedupeKey($bot->botKey(), $event, $payload, $room);
            if (! Cache::add($dedupeKey, true, now()->addMinutes(2))) {
                continue;
            }

            try {
                $bot->handleEvent($event, $payload);
            } catch (\Throwable $e) {
                Log::warning('bot.event.error', [
                    'bot'   => $bot->botKey(),
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /** Check if the given text looks like a bot command. */
    public function isCommand(string $text): bool
    {
        return $this->parser->isCommand($text);
    }

    public function getBot(string $key): ?BotInterface
    {
        return $this->bots[$key] ?? null;
    }

    private function eventDedupeKey(string $botKey, string $event, array $payload, ?Room $room): string
    {
        $eventId = $payload['event_id']
            ?? $payload['id']
            ?? $payload['message_id']
            ?? sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return sprintf('bot:event:%s:%s:%s:%s', $botKey, $event, $room?->id ?? 'global', $eventId);
    }

    private function commandCooldownKey(string $botKey, BotCommandContext $ctx): string
    {
        return sprintf('bot:command:%s:%s:%s:%s', $botKey, $ctx->room->id, $ctx->sender->id, sha1($ctx->rawText));
    }

    private function roomBotEnabled(Bot $bot, Room $room): bool
    {
        $roomBot = $bot->roomBots()->where('room_id', $room->id)->first();

        return $roomBot?->is_enabled ?? true;
    }
}
