<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\MusicStateChanged;
use App\Events\VoiceStateChanged;
use App\Models\Message;
use App\Models\RoomMusicState;
use App\Support\AppMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MusicController extends Controller
{
    private const DJ_BOT_USER_ID = 0;

    private const DJ_BOT_USERNAME = '🎵 DJ Bot';

    /** Return current music state (with live-computed position).
     *  Also auto-advances track if it has ended. */
    public function getState($roomId)
    {
        $state = RoomMusicState::firstOrCreate(
            ['room_id' => $roomId],
            ['queue' => []]
        );

        if ($state->video_id && $state->is_playing && $state->video_duration > 0) {
            if ($state->current_position >= $state->video_duration) {
                $this->doNext($state, $roomId);
                $state->refresh();
            }
        }

        return response()->json($this->formatState($state));
    }

    private function broadcast(RoomMusicState $state): void
    {
        try {
            broadcast(new MusicStateChanged($state->room_id, $this->formatState($state)));
        } catch (\Throwable $e) {
            Log::warning('music.broadcast.state_failed', [
                'room_id' => $state->room_id,
                'error' => $e->getMessage(),
            ]);
            AppMetrics::increment('external_service_errors_total', ['service' => 'reverb', 'reason' => 'music_state_broadcast']);
        }
    }

    /** Slash command handler — called from chat input */
    public function handleCommand(Request $request, $roomId)
    {
        $request->validate(['command' => 'required|string|max:500']);
        $raw = trim($request->command);
        $user = Auth::user();

        /* Check user is in voice chat */
        $inVoice = DB::table('voice_sessions')
            ->where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $inVoice) {
            return response()->json(['error' => 'Önce sesli sohbete katılmalısınız'], 422);
        }

        /* Parse command */
        if (preg_match('/^\/play\s+(.+)$/iu', $raw, $m)) {
            return $this->cmdPlay($roomId, trim($m[1]), $user);
        }
        if (preg_match('/^\/(?:durdur|stop|pause)$/iu', $raw)) {
            return $this->cmdStop($roomId, $user);
        }
        if (preg_match('/^\/(?:ge[cç]|skip|next)$/iu', $raw)) {
            return $this->cmdSkip($roomId, $user);
        }
        if (preg_match('/^\/(?:s[ıi]ra|queue)$/iu', $raw)) {
            return $this->cmdQueue($roomId);
        }
        if (preg_match('/^\/(?:devam|resume)$/iu', $raw)) {
            return $this->cmdResume($roomId, $user);
        }

        return response()->json(['error' => 'Bilinmeyen komut. Kullanım: /play, /durdur, /geç, /sıra, /devam'], 422);
    }

    /** /play <query|url> */
    private function cmdPlay($roomId, string $query, $user)
    {
        /* Detect if it's a YouTube URL or a search query */
        $videoId = $this->extractVideoId($query);
        if (! $videoId) {
            $videoId = $this->searchYouTube($query);
        }
        if (! $videoId) {
            return response()->json(['error' => 'Video bulunamadı: '.$query], 404);
        }

        $state = RoomMusicState::firstOrCreate(
            ['room_id' => $roomId],
            ['queue' => []]
        );

        $title = $this->getVideoTitle($videoId);
        $duration = $this->getVideoDuration($videoId);

        if (! $state->video_id) {
            /* No current track — play immediately */
            $state->fill([
                'video_id' => $videoId,
                'video_title' => $title,
                'video_duration' => $duration,
                'is_playing' => true,
                'position' => 0,
                'started_at_unix' => time(),
                'state_updated_at' => now(),
                'updated_by' => $user->id,
            ])->save();
            $this->djBotJoin($roomId);
            $this->postSystemMessage($roomId, "🎵 Şu an çalıyor: {$title}");
        } else {
            /* Add to queue */
            $queue = $state->queue ?? [];
            $queue[] = ['video_id' => $videoId, 'title' => $title, 'duration' => $duration];
            $state->fill(['queue' => $queue, 'updated_by' => $user->id])->save();
            $this->postSystemMessage($roomId, "➕ Sıraya eklendi: {$title} (#{$this->queuePosition($queue, $videoId)})");
        }

        $this->broadcast($state);

        return response()->json(['ok' => true, 'state' => $this->formatState($state)]);
    }

    /** /durdur */
    private function cmdStop($roomId, $user)
    {
        $state = RoomMusicState::where('room_id', $roomId)->first();
        if (! $state || ! $state->video_id) {
            return response()->json(['error' => 'Şu an çalan bir şarkı yok'], 422);
        }

        $curPos = $state->current_position;
        $state->fill([
            'is_playing' => false,
            'position' => $curPos,
            'started_at_unix' => null,
            'state_updated_at' => now(),
            'updated_by' => $user->id,
        ])->save();

        $this->djBotLeave($roomId);
        $this->postSystemMessage($roomId, '⏸ Müzik duraklatıldı');
        $this->broadcast($state);

        return response()->json(['ok' => true, 'state' => $this->formatState($state)]);
    }

    /** /devam */
    private function cmdResume($roomId, $user)
    {
        $state = RoomMusicState::where('room_id', $roomId)->first();
        if (! $state || ! $state->video_id) {
            return response()->json(['error' => 'Devam edilecek şarkı yok'], 422);
        }

        $pos = (float) ($state->position ?? 0);
        $state->fill([
            'is_playing' => true,
            'position' => $pos,
            'started_at_unix' => time() - (int) $pos,
            'state_updated_at' => now(),
            'updated_by' => $user->id,
        ])->save();

        $this->djBotJoin($roomId);
        $this->postSystemMessage($roomId, "▶ Devam ediyor: {$state->video_title}");
        $this->broadcast($state);

        return response()->json(['ok' => true, 'state' => $this->formatState($state)]);
    }

    /** /geç */
    private function cmdSkip($roomId, $user)
    {
        $state = RoomMusicState::where('room_id', $roomId)->first();
        if (! $state || ! $state->video_id) {
            return response()->json(['error' => 'Geçilecek şarkı yok'], 422);
        }

        $oldTitle = $state->video_title;
        $this->doNext($state, $roomId);
        $state->refresh();

        if ($state->video_id) {
            $this->postSystemMessage($roomId, "⏭ Geçildi: {$oldTitle} → Şu an: {$state->video_title}");
        } else {
            $this->djBotLeave($roomId);
            $this->postSystemMessage($roomId, "⏭ {$oldTitle} geçildi. Sırada şarkı kalmadı.");
        }

        $this->broadcast($state);

        return response()->json(['ok' => true, 'state' => $this->formatState($state)]);
    }

    /** /sıra */
    private function cmdQueue($roomId)
    {
        $state = RoomMusicState::where('room_id', $roomId)->first();
        $lines = [];
        if ($state && $state->video_id) {
            $lines[] = "🎵 Şu an: {$state->video_title}";
        }
        $queue = $state->queue ?? [];
        if (count($queue) > 0) {
            foreach ($queue as $i => $item) {
                $lines[] = ($i + 1).". {$item['title']}";
            }
        } else {
            $lines[] = 'Sırada şarkı yok.';
        }
        $this->postSystemMessage($roomId, implode("\n", $lines));

        return response()->json(['ok' => true, 'queue' => $queue]);
    }

    /* ── DJ Bot voice presence (virtual — no DB row) ── */

    private function djBotJoin(int $roomId): void
    {
        $this->broadcastVoiceState($roomId, true);
    }

    private function djBotLeave(int $roomId): void
    {
        $this->broadcastVoiceState($roomId, false);
    }

    private function broadcastVoiceState(int $roomId, bool $includeDjBot = false): void
    {
        $participants = DB::table('voice_sessions')
            ->join('users', 'users.id', '=', 'voice_sessions.user_id')
            ->where('voice_sessions.room_id', $roomId)
            ->where('voice_sessions.is_active', true)
            ->select(
                'users.id',
                'users.username',
                'users.avatar_url',
                'voice_sessions.is_muted',
                'voice_sessions.is_deafened',
                'voice_sessions.is_speaking',
                'voice_sessions.can_speak',
                'voice_sessions.connection_quality',
                'voice_sessions.reconnect_count'
            )
            ->get()
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'username' => $p->username,
                'avatar_url' => $p->avatar_url,
                'is_muted' => (bool) $p->is_muted,
                'is_deafened' => (bool) $p->is_deafened,
                'is_speaking' => (bool) $p->is_speaking,
                'can_speak' => (bool) $p->can_speak,
                'connection_quality' => $p->connection_quality ?? 'unknown',
                'reconnect_count' => (int) $p->reconnect_count,
            ])
            ->toArray();

        if ($includeDjBot) {
            $participants[] = [
                'id' => self::DJ_BOT_USER_ID,
                'username' => self::DJ_BOT_USERNAME,
                'avatar_url' => null,
                'is_muted' => true,
                'is_deafened' => false,
                'is_speaking' => false,
                'can_speak' => false,
                'connection_quality' => 'unknown',
                'reconnect_count' => 0,
            ];
        }

        try {
            broadcast(new VoiceStateChanged($roomId, $participants));
        } catch (\Throwable $e) {
            Log::warning('music.broadcast.voice_state_failed', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
            AppMetrics::increment('external_service_errors_total', ['service' => 'reverb', 'reason' => 'music_voice_state_broadcast']);
        }
    }

    /* ── System message in chat ── */

    private function postSystemMessage(int $roomId, string $content): void
    {
        $message = Message::create([
            'room_id' => $roomId,
            'sender_id' => Auth::id(),
            'content' => $content,
            'is_system_message' => true,
        ]);

        $message->load('sender');

        try {
            broadcast(new MessageSent($roomId, [
                'id' => $message->id,
                'title' => $message->title,
                'content' => $message->content,
                'audio_url' => null,
                'audio_duration' => null,
                'is_system_message' => true,
                'reply_to' => null,
                'reply_message' => null,
                'created_at' => $message->created_at->toISOString(),
                'sender' => $message->sender ? [
                    'id' => $message->sender->id,
                    'username' => $message->sender->username,
                    'avatar_url' => $message->sender->avatar_url,
                    'role' => $message->sender->role,
                ] : null,
            ]));
        } catch (\Throwable $e) {
            Log::warning('music.broadcast.system_message_failed', [
                'room_id' => $roomId,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
            AppMetrics::increment('external_service_errors_total', ['service' => 'reverb', 'reason' => 'music_system_message_broadcast']);
        }
    }

    /* ── YouTube search ── */

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
        /* Try scraping YouTube search results page (no API key needed) */
        try {
            $url = 'https://www.youtube.com/results?search_query='.urlencode($query);
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'ignore_errors' => true,
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\nAccept-Language: en-US,en;q=0.9\r\n",
                ],
            ]);
            $html = @file_get_contents($url, false, $ctx);
            if ($html && preg_match('/\/watch\?v=([\w-]{11})/', $html, $m)) {
                return $m[1];
            }
        } catch (\Exception $e) {
            Log::warning('music.youtube.scrape_failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            AppMetrics::increment('external_service_errors_total', ['service' => 'youtube', 'reason' => 'scrape_failed']);
        }

        /* Fallback: YouTube Data API if key is configured */
        $apiKey = config('services.youtube.key', env('YOUTUBE_API_KEY', ''));
        if ($apiKey) {
            try {
                $url = 'https://www.googleapis.com/youtube/v3/search?part=snippet'
                    .'&type=video&maxResults=1&videoEmbeddable=true'
                    .'&q='.urlencode($query)
                    .'&key='.urlencode($apiKey);
                $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
                $json = @file_get_contents($url, false, $ctx);
                if ($json) {
                    $data = json_decode($json, true);

                    return $data['items'][0]['id']['videoId'] ?? null;
                }
            } catch (\Exception $e) {
                Log::warning('music.youtube.search_api_failed', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);
                AppMetrics::increment('external_service_errors_total', ['service' => 'youtube', 'reason' => 'search_api_failed']);
            }
        }

        return null;
    }

    private function queuePosition(array $queue, string $videoId): int
    {
        foreach ($queue as $i => $item) {
            if ($item['video_id'] === $videoId) {
                return $i + 1;
            }
        }

        return count($queue);
    }

    /** Advance to next queued track (or stop if queue empty). */
    private function doNext(RoomMusicState $state, ?int $roomId = null): void
    {
        $queue = $state->queue ?? [];
        $rid = $roomId ?? $state->room_id;
        if (count($queue) > 0) {
            $next = array_shift($queue);
            $state->fill([
                'video_id' => $next['video_id'],
                'video_title' => $next['title'] ?? $next['video_id'],
                'video_duration' => $next['duration'] ?? 0,
                'is_playing' => true,
                'position' => 0,
                'started_at_unix' => time(),
                'queue' => $queue,
                'state_updated_at' => now(),
                'updated_by' => Auth::id(),
            ])->save();
        } else {
            $state->fill([
                'video_id' => null,
                'video_title' => null,
                'video_duration' => 0,
                'is_playing' => false,
                'position' => 0,
                'started_at_unix' => null,
                'state_updated_at' => now(),
                'updated_by' => Auth::id(),
            ])->save();
            $this->djBotLeave($rid);
        }
    }

    private function getVideoTitle(string $videoId): string
    {
        try {
            $url = 'https://www.youtube.com/oembed?url='.urlencode('https://www.youtube.com/watch?v='.$videoId).'&format=json';
            $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
            $json = @file_get_contents($url, false, $ctx);
            if ($json) {
                $data = json_decode($json, true);
                if (! empty($data['title'])) {
                    return $data['title'];
                }
            }
        } catch (\Exception $e) {
            Log::warning('music.youtube.oembed_failed', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);
            AppMetrics::increment('external_service_errors_total', ['service' => 'youtube', 'reason' => 'oembed_failed']);
        }

        return $videoId;
    }

    /** Fetch video duration in seconds from YouTube Data API. Returns 0 on failure. */
    private function getVideoDuration(string $videoId): float
    {
        try {
            $apiKey = config('services.youtube.key', env('YOUTUBE_API_KEY', ''));
            if (! $apiKey) {
                return 0;
            }
            $url = 'https://www.googleapis.com/youtube/v3/videos?part=contentDetails&id='
                .urlencode($videoId).'&key='.urlencode($apiKey);
            $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
            $json = @file_get_contents($url, false, $ctx);
            if ($json) {
                $data = json_decode($json, true);
                $iso = $data['items'][0]['contentDetails']['duration'] ?? null;
                if ($iso) {
                    return (float) $this->iso8601ToSeconds($iso);
                }
            }
        } catch (\Exception $e) {
            Log::warning('music.youtube.duration_failed', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
            ]);
            AppMetrics::increment('external_service_errors_total', ['service' => 'youtube', 'reason' => 'duration_failed']);
        }

        return 0;
    }

    /** Convert ISO 8601 duration (PT4M13S) to seconds. */
    private function iso8601ToSeconds(string $iso): int
    {
        preg_match('/PT(?:([0-9]+)H)?(?:([0-9]+)M)?(?:([0-9]+)S)?/', $iso, $m);

        return (int) ($m[1] ?? 0) * 3600
             + (int) ($m[2] ?? 0) * 60
             + (int) ($m[3] ?? 0);
    }

    private function formatState(RoomMusicState $state): array
    {
        return [
            'video_id' => $state->video_id,
            'video_title' => $state->video_title,
            'video_duration' => (float) $state->video_duration,
            'is_playing' => (bool) $state->is_playing,
            'position' => $state->position,
            'started_at_unix' => $state->started_at_unix, /* key for client-side sync */
            'current_position' => $state->current_position,
            'queue' => $state->queue ?? [],
        ];
    }
}
