<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class AppMetrics
{
    private const INDEX_KEY = 'metrics:index';

    private const TTL_DAYS = 7;

    public static function increment(string $name, array $tags = [], int $by = 1): void
    {
        $key = self::key($name, $tags);
        self::index($key);

        if (! Cache::has($key)) {
            Cache::put($key, 0, now()->addDays(self::TTL_DAYS));
        }

        Cache::increment($key, $by);
    }

    public static function gauge(string $name, int|float $value, array $tags = []): void
    {
        $key = self::key($name, $tags);
        self::index($key);
        Cache::put($key, $value, now()->addDays(self::TTL_DAYS));
    }

    public static function snapshot(): array
    {
        $keys = Cache::get(self::INDEX_KEY, []);
        $metrics = [];

        foreach ($keys as $key) {
            $metrics[$key] = Cache::get($key, 0);
        }

        ksort($metrics);

        return $metrics;
    }

    private static function key(string $name, array $tags): string
    {
        ksort($tags);

        $tagString = collect($tags)
            ->map(fn ($value, $key) => $key.'='.$value)
            ->implode(',');

        return $tagString === ''
            ? 'metric:'.$name
            : 'metric:'.$name.'{'.$tagString.'}';
    }

    private static function index(string $key): void
    {
        $keys = Cache::get(self::INDEX_KEY, []);
        $keys[] = $key;
        Cache::put(self::INDEX_KEY, array_values(array_unique($keys)), now()->addDays(self::TTL_DAYS));
    }
}
