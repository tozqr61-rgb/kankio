<?php

namespace App\Services\Bots;

use App\Models\Room;
use App\Models\User;

class BotCommandParser
{
    private const SLASH = '/';

    public function isCommand(string $text): bool
    {
        return str_starts_with(ltrim($text), self::SLASH);
    }

    public function parse(string $text, Room $room, User $sender): ?BotCommandContext
    {
        $text = trim($text);

        if (! str_starts_with($text, self::SLASH)) {
            return null;
        }

        $parts = preg_split('/\s+/', mb_substr($text, 1), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($parts)) {
            return null;
        }

        $command = mb_strtolower(array_shift($parts));
        $args    = $parts;

        return new BotCommandContext(
            command: $command,
            args:    $args,
            rawText: $text,
            room:    $room,
            sender:  $sender,
        );
    }
}
