<?php

namespace App\Services\Bots;

class BotResponse
{
    public function __construct(
        public readonly bool   $handled,
        public readonly string $message = '',
        public readonly array  $data = [],
    ) {}

    public static function ok(string $message = '', array $data = []): self
    {
        return new self(true, $message, $data);
    }

    public static function error(string $message): self
    {
        return new self(true, $message, ['error' => true]);
    }

    public static function ignored(): self
    {
        return new self(false);
    }
}
