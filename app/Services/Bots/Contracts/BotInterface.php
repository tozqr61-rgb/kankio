<?php

namespace App\Services\Bots\Contracts;

use App\Models\Room;
use App\Services\Bots\BotCommandContext;
use App\Services\Bots\BotResponse;

interface BotInterface
{
    public function botKey(): string;

    public function supportsRoom(Room $room): bool;

    public function supportsCommand(string $command): bool;

    public function handleCommand(BotCommandContext $ctx): BotResponse;

    public function handleEvent(string $event, array $payload): void;
}
