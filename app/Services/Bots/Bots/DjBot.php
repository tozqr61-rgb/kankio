<?php

namespace App\Services\Bots\Bots;

use App\Events\MusicStateChanged;
use App\Models\Bot;
use App\Models\Room;
use App\Models\RoomMusicState;
use App\Services\Bots\BotCommandContext;
use App\Services\Bots\BotMessageService;
use App\Services\Bots\BotResponse;
use App\Services\Bots\Contracts\BotInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DjBot implements BotInterface
{
    public const BOT_KEY = 'dj_bot';

    private const COMMANDS = ['çal', 'cal', 'play', 'geç', 'gec', 'skip', 'next',
        'durdur', 'stop', 'pause', 'devam', 'resume',
        'sıra', 'sira', 'queue', 'müzik', 'muzik', 'dj'];

    public function __construct(
        private BotMessageService $messenger,
    ) {}

    public function botKey(): string
    {
        return self::BOT_KEY;
    }

    public function supportsRoom(Room $room): bool
    {
        return true;
    }

    public function supportsCommand(string $command): bool
    {
        return in_array(mb_strtolower($command), self::COMMANDS, true);
    }

    public function handleCommand(BotCommandContext $ctx): BotResponse
    {
        $bot = Bot::findByKey(self::BOT_KEY);
        if (! $bot) {
            return BotResponse::ignored();
        }

        $cmd = mb_strtolower($ctx->command);

        return match (true) {
            in_array($cmd, ['çal', 'cal', 'play'], true)          => $this->cmdPlay($ctx, $bot),
            in_array($cmd, ['geç', 'gec', 'skip', 'next'], true)  => $this->cmdSkip($ctx, $bot),
            in_array($cmd, ['durdur', 'stop', 'pause'], true)      => $this->cmdStop($ctx, $bot),
            in_array($cmd, ['devam', 'resume'], true)              => $this->cmdResume($ctx, $bot),
            in_array($cmd, ['sıra', 'sira', 'queue'], true)        => $this->cmdQueue($ctx, $bot),
            in_array($cmd, ['müzik', 'muzik', 'dj'], true)         => $this->help($ctx, $bot),
            default                                                 => $this->help($ctx, $bot),
        };
    }

    public function handleEvent(string $event, array $payload): void
    {
        $bot = Bot::findByKey(self::BOT_KEY);
        if (! $bot) {
            return;
        }

        match ($event) {
            'music.track_changed' => $this->onTrackChanged($bot, $payload),
            'music.queue_empty'   => $this->onQueueEmpty($bot, $payload),
            default               => null,
        };
    }

    private function cmdPlay(BotCommandContext $ctx, Bot $bot): BotResponse
    {
        $query = $ctx->argString();
        if (! $query) {
            $this->messenger->send($ctx->room, $bot, '❌ Kullanım: `/çal <şarkı adı veya YouTube URL>`');

            return BotResponse::error('Sorgu boş.');
        }

        $videoId = $this->extractVideoId($query) ?? $this->searchYouTube($query);

        if (! $videoId) {
            $this->messenger->send($ctx->room, $bot, "❌ Bulunamadı: **{$query}**");

            return BotResponse::error('Video bulunamadı.');
        }

        $state = RoomMusicState::firstOrCreate(
            ['room_id' => $ctx->room->id],
            ['queue' => []]
        );

        $title    = $this->getVideoTitle($videoId);
        $duration = $this->getVideoDuration($videoId);

        if (! $state->video_id) {
            $state->fill([
                'video_id'         => $videoId,
                'video_title'      => $title,
                'video_duration'   => $duration,
                'is_playing'       => true,
                'position'         => 0,
                'started_at_unix'  => time(),
                'state_updated_at' => now(),
                'updated_by'       => $ctx->sender->id,
            ])->save();
            $this->broadcastState($state);
            $this->messenger->send($ctx->room, $bot, "🎵 Şu an çalıyor: **{$title}**");
        } else {
            $queue   = $state->queue ?? [];
            $queue[] = ['video_id' => $videoId, 'title' => $title, 'duration' => $duration];
            $state->fill(['queue' => $queue, 'updated_by' => $ctx->sender->id])->save();
            $this->broadcastState($state);
            $pos = count($queue);
            $this->messenger->send($ctx->room, $bot, "➕ Sıraya eklendi: **{$title}** (#{$pos})");
        }

        return BotResponse::ok();
    }

    private function cmdSkip(BotCommandContext $ctx, Bot $bot): BotResponse
    {
        $state = RoomMusicState::where('room_id', $ctx->room->id)->first();

        if (! $state || ! $state->video_id) {
            $this->messenger->send($ctx->room, $bot, '❌ Geçilecek şarkı yok.');

            return BotResponse::error('Şarkı yok.');
        }

        $oldTitle = $state->video_title;
        $this->doNext($state, $ctx->room->id);
        $state->refresh();

        if ($state->video_id) {
            $this->messenger->send($ctx->room, $bot, "⏭ Geçildi → Şu an: **{$state->video_title}**");
        } else {
            $this->messenger->send($ctx->room, $bot, "⏭ **{$oldTitle}** geçildi. Sırada şarkı kalmadı.");
        }

        return BotResponse::ok();
    }

    private function cmdStop(BotCommandContext $ctx, Bot $bot): BotResponse
    {
        $state = RoomMusicState::where('room_id', $ctx->room->id)->first();

        if (! $state || ! $state->video_id) {
            $this->messenger->send($ctx->room, $bot, '❌ Şu an çalan bir şarkı yok.');

            return BotResponse::error('Şarkı yok.');
        }

        $state->fill([
            'is_playing'       => false,
            'position'         => $state->current_position,
            'started_at_unix'  => null,
            'state_updated_at' => now(),
            'updated_by'       => $ctx->sender->id,
        ])->save();

        $this->broadcastState($state);
        $this->messenger->send($ctx->room, $bot, '⏸ Müzik duraklatıldı.');

        return BotResponse::ok();
    }

    private function cmdResume(BotCommandContext $ctx, Bot $bot): BotResponse
    {
        $state = RoomMusicState::where('room_id', $ctx->room->id)->first();

        if (! $state || ! $state->video_id) {
            $this->messenger->send($ctx->room, $bot, '❌ Devam edilecek şarkı yok.');

            return BotResponse::error('Şarkı yok.');
        }

        $pos = (float) ($state->position ?? 0);
        $state->fill([
            'is_playing'       => true,
            'position'         => $pos,
            'started_at_unix'  => time() - (int) $pos,
            'state_updated_at' => now(),
            'updated_by'       => $ctx->sender->id,
        ])->save();

        $this->broadcastState($state);
        $this->messenger->send($ctx->room, $bot, "▶ Devam ediyor: **{$state->video_title}**");

        return BotResponse::ok();
    }

    private function cmdQueue(BotCommandContext $ctx, Bot $bot): BotResponse
    {
        $state = RoomMusicState::where('room_id', $ctx->room->id)->first();
        $lines = [];

        if ($state && $state->video_id) {
            $lines[] = "🎵 Şu an: **{$state->video_title}**";
        }

        $queue = $state?->queue ?? [];
        if (count($queue) > 0) {
            foreach ($queue as $i => $item) {
                $lines[] = ($i + 1) . '. ' . ($item['title'] ?? '?');
            }
        } else {
            $lines[] = 'Sırada şarkı yok.';
        }

        $this->messenger->send($ctx->room, $bot, implode("\n", $lines));

        return BotResponse::ok();
    }

    private function help(BotCommandContext $ctx, Bot $bot): BotResponse
    {
        $this->messenger->send($ctx->room, $bot, implode("\n", [
            '🎵 **DJ Bot Komutları:**',
            '`/çal <şarkı>` — YouTube\'da ara ve çal',
            '`/geç` — Sıradaki şarkıya geç',
            '`/durdur` — Müziği duraklat',
            '`/devam` — Devam et',
            '`/sıra` — Çalma listesini göster',
        ]));

        return BotResponse::ok();
    }

    private function onTrackChanged(Bot $bot, array $payload): void
    {
        $room = \App\Models\Room::find($payload['room_id'] ?? null);
        if (! $room) {
            return;
        }
        $title = $payload['video_title'] ?? '?';
        $this->messenger->send($room, $bot, "🎵 Şu an çalıyor: **{$title}**");
    }

    private function onQueueEmpty(Bot $bot, array $payload): void
    {
        $room = \App\Models\Room::find($payload['room_id'] ?? null);
        if (! $room) {
            return;
        }
        $this->messenger->send($room, $bot, '🎵 Çalma listesi bitti.');
    }

    private function doNext(RoomMusicState $state, int $roomId): void
    {
        $queue = $state->queue ?? [];
        if (count($queue) > 0) {
            $next = array_shift($queue);
            $state->fill([
                'video_id'         => $next['video_id'],
                'video_title'      => $next['title'] ?? '',
                'video_duration'   => $next['duration'] ?? 0,
                'is_playing'       => true,
                'position'         => 0,
                'started_at_unix'  => time(),
                'queue'            => $queue,
                'state_updated_at' => now(),
            ])->save();
        } else {
            $state->fill([
                'video_id'    => null,
                'video_title' => null,
                'is_playing'  => false,
                'queue'       => [],
            ])->save();
        }
        $this->broadcastState($state);
    }

    private function broadcastState(RoomMusicState $state): void
    {
        try {
            broadcast(new MusicStateChanged($state->room_id, $state->toArray()));
        } catch (\Throwable $e) {
            Log::warning('dj_bot.broadcast.failed', ['error' => $e->getMessage()]);
        }
    }

    private function extractVideoId(string $input): ?string
    {
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([\w-]{11})/', $input, $m)) {
            return $m[1];
        }
        if (preg_match('/^[\w-]{11}$/', $input)) {
            return $input;
        }

        return null;
    }

    private function searchYouTube(string $query): ?string
    {
        return Cache::remember('music.youtube.search.' . sha1($query), now()->addHours(6), function () use ($query) {
            $apiKey = config('services.youtube.key', '');
            if (! $apiKey) {
                return null;
            }
            try {
                $response = Http::timeout(5)->get('https://www.googleapis.com/youtube/v3/search', [
                    'part'           => 'snippet',
                    'type'           => 'video',
                    'maxResults'     => 1,
                    'videoEmbeddable'=> 'true',
                    'q'              => $query,
                    'key'            => $apiKey,
                ]);

                return $response->ok() ? $response->json('items.0.id.videoId') : null;
            } catch (\Throwable $e) {
                Log::warning('dj_bot.youtube.search_failed', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    private function getVideoTitle(string $videoId): string
    {
        return Cache::remember('music.youtube.title.' . $videoId, now()->addHours(24), function () use ($videoId) {
            $apiKey = config('services.youtube.key', '');
            if (! $apiKey) {
                return $videoId;
            }
            try {
                $response = Http::timeout(5)->get('https://www.googleapis.com/youtube/v3/videos', [
                    'part' => 'snippet',
                    'id'   => $videoId,
                    'key'  => $apiKey,
                ]);

                return $response->ok() ? ($response->json('items.0.snippet.title') ?? $videoId) : $videoId;
            } catch (\Throwable $e) {
                return $videoId;
            }
        });
    }

    private function getVideoDuration(string $videoId): int
    {
        return Cache::remember('music.youtube.duration.' . $videoId, now()->addHours(24), function () use ($videoId) {
            $apiKey = config('services.youtube.key', '');
            if (! $apiKey) {
                return 0;
            }
            try {
                $response = Http::timeout(5)->get('https://www.googleapis.com/youtube/v3/videos', [
                    'part' => 'contentDetails',
                    'id'   => $videoId,
                    'key'  => $apiKey,
                ]);
                if (! $response->ok()) {
                    return 0;
                }
                $iso = $response->json('items.0.contentDetails.duration') ?? 'PT0S';
                preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $iso, $m);

                return ((int) ($m[1] ?? 0)) * 3600 + ((int) ($m[2] ?? 0)) * 60 + (int) ($m[3] ?? 0);
            } catch (\Throwable $e) {
                return 0;
            }
        });
    }
}
