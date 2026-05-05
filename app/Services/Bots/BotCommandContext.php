<?php

namespace App\Services\Bots;

use App\Models\Room;
use App\Models\User;

class BotCommandContext
{
    public function __construct(
        public readonly string $command,
        public readonly array  $args,
        public readonly string $rawText,
        public readonly Room   $room,
        public readonly User   $sender,
    ) {}

    public function arg(int $index, mixed $default = null): mixed
    {
        return $this->args[$index] ?? $default;
    }

    public function argString(): string
    {
        return implode(' ', $this->args);
    }
}
