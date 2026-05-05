<?php

namespace App\Services\Games;

use App\Events\GameSessionUpdated;
use App\Models\GameParticipant;
use App\Models\GameRound;
use App\Models\GameSession;
use App\Models\GameSubmission;
use App\Models\Room;
use App\Models\User;
use App\Support\AppMetrics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class IsimSehirGameService
{
    public const DEFAULT_CATEGORIES = ['isim', 'şehir', 'hayvan', 'eşya', 'bitki'];

    public const DEFAULT_ROUND_SECONDS = 420;

    private const ACTIVE_STATUSES = ['waiting', 'in_progress'];

    public function __construct(
        private GameScoringService $scoring,
        private GameStateService $stateService,
    ) {}

    public function current(Room $room): ?GameSession
    {
        return GameSession::where('room_id', $room->id)
            ->where('game_type', 'isim_sehir')
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->latest()
            ->first();
    }

    public function start(Room $room, User $user, array $settings = []): GameSession
    {
        return DB::transaction(function () use ($room, $user, $settings) {
            Room::whereKey($room->id)->lockForUpdate()->firstOrFail();

            $existing = GameSession::where('room_id', $room->id)
                ->where('game_type', 'isim_sehir')
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->status === 'waiting') {
                    $this->applyWaitingSettings($existing, $settings);
                }

                return $existing;
            }

            $normalizedSettings = $this->normalizeSettings($settings);

            $session = GameSession::create([
                'room_id' => $room->id,
                'created_by' => $user->id,
                'game_type' => 'isim_sehir',
                'status' => 'waiting',
                'round_time_seconds' => $normalizedSettings['round_time_seconds'],
                'last_activity_at' => now(),
                'settings' => $normalizedSettings,
            ]);

            $this->join($session, $user, false);

            return $session;
        });
    }

    public function join(GameSession $session, User $user, bool $broadcast = true): GameParticipant
    {
        $participant = GameParticipant::updateOrCreate(
            ['game_session_id' => $session->id, 'user_id' => $user->id],
            [
                'joined_at' => now(),
                'left_at' => null,
                'is_active' => true,
            ]
        );

        $session->update(['last_activity_at' => now()]);
        if ($broadcast) {
            $this->broadcast($session, $user, 'participant.joined');
        }

        return $participant;
    }

    public function leave(GameSession $session, User $user): void
    {
        $cancelled = DB::transaction(function () use ($session, $user) {
            $session = GameSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            GameParticipant::where('game_session_id', $session->id)
                ->where('user_id', $user->id)
                ->update(['is_active' => false, 'left_at' => now(), 'is_ready' => false]);

            $hasActiveParticipants = GameParticipant::where('game_session_id', $session->id)
                ->where('is_active', true)
                ->exists();

            if (! $hasActiveParticipants && in_array($session->status, self::ACTIVE_STATUSES, true)) {
                GameRound::where('game_session_id', $session->id)
                    ->where('status', 'collecting')
                    ->update([
                        'status' => 'closed',
                        'ended_at' => now(),
                    ]);

                $session->update([
                    'status' => 'cancelled',
                    'ended_at' => now(),
                    'last_activity_at' => now(),
                ]);

                return true;
            }

            $session->update(['last_activity_at' => now()]);

            return false;
        });

        $this->broadcast($session, $user, $cancelled ? 'game.cancelled' : 'participant.left');
    }

    public function ready(GameSession $session, User $user, bool $ready): void
    {
        $this->requireParticipant($session, $user);
        GameParticipant::where('game_session_id', $session->id)
            ->where('user_id', $user->id)
            ->update(['is_ready' => $ready]);

        $session->update(['last_activity_at' => now()]);
        $this->broadcast($session, $user, 'participant.ready');
    }

    public function updateSettings(GameSession $session, User $user, array $settings): void
    {
        DB::transaction(function () use ($session, $settings) {
            $session = GameSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (in_array($session->status, ['finished', 'cancelled'], true)) {
                throw ValidationException::withMessages(['game' => 'Bitmiş oyunun ayarları değiştirilemez.']);
            }

            $hasCollectingRound = GameRound::where('game_session_id', $session->id)
                ->where('status', 'collecting')
                ->exists();

            if ($hasCollectingRound) {
                throw ValidationException::withMessages(['game' => 'Tur devam ederken ayarlar değiştirilemez.']);
            }

            $normalizedSettings = $this->normalizeSettings($settings);

            $session->update([
                'round_time_seconds' => $normalizedSettings['round_time_seconds'],
                'settings' => $normalizedSettings,
                'last_activity_at' => now(),
            ]);
        });

        $this->broadcast($session, $user, 'settings.updated');
    }

    public function beginRound(GameSession $session, User $user): GameRound
    {
        return DB::transaction(function () use ($session, $user) {
            $session = GameSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status === 'finished' || $session->status === 'cancelled') {
                throw ValidationException::withMessages(['game' => 'Bu oyun bitmiş. Yeni tur başlatılamaz.']);
            }
            if (! GameParticipant::where('game_session_id', $session->id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['game' => 'Tur başlatmak için en az bir aktif oyuncu olmalı.']);
            }

            $activeRound = GameRound::where('game_session_id', $session->id)
                ->where('status', 'collecting')
                ->first();

            if ($activeRound) {
                return $activeRound;
            }

            $roundNo = (int) $session->current_round_no + 1;
            $round = GameRound::create([
                'game_session_id' => $session->id,
                'round_no' => $roundNo,
                'letter' => $this->randomLetter(),
                'status' => 'collecting',
                'started_at' => now(),
                'submission_deadline' => now()->addSeconds((int) $session->round_time_seconds),
            ]);

            $session->update([
                'status' => 'in_progress',
                'current_round_no' => $roundNo,
                'started_at' => $session->started_at ?: now(),
                'last_activity_at' => now(),
            ]);

            GameParticipant::where('game_session_id', $session->id)->update(['is_ready' => false]);
            $this->broadcast($session, $user, 'round.started');

            return $round;
        });
    }

    public function saveDraft(GameSession $session, GameRound $round, User $user, array $answers): GameSubmission
    {
        $this->ensureRoundBelongsToSession($session, $round);
        $this->requireParticipant($session, $user);

        $submission = GameSubmission::firstOrNew([
            'game_round_id' => $round->id,
            'user_id' => $user->id,
        ]);

        if ($round->status !== 'collecting') {
            throw ValidationException::withMessages(['round' => 'Bu tur kapandı.']);
        }

        if ($submission->exists && $submission->is_locked) {
            return $submission;
        }

        $submission->fill(['answers' => $this->sanitizeAnswers($session, $answers)])->save();

        return $submission;
    }

    public function submit(GameSession $session, GameRound $round, User $user, array $answers): GameSubmission
    {
        $submission = DB::transaction(function () use ($session, $round, $user, $answers) {
            $this->ensureRoundBelongsToSession($session, $round);
            $this->requireParticipant($session, $user);
            $round = GameRound::whereKey($round->id)->lockForUpdate()->firstOrFail();

            if ($round->status !== 'collecting') {
                throw ValidationException::withMessages(['round' => 'Bu tur kapandı.']);
            }

            if ($round->submission_deadline && now()->greaterThan($round->submission_deadline)) {
                $this->finalizeRound($session, $round, $user, false);
                throw ValidationException::withMessages(['round' => 'Süre bitti.']);
            }

            return GameSubmission::updateOrCreate(
                ['game_round_id' => $round->id, 'user_id' => $user->id],
                [
                    'answers' => $this->sanitizeAnswers($session, $answers),
                    'submitted_at' => now(),
                    'is_locked' => true,
                ]
            );
        });

        $this->finalizeIfEveryoneSubmitted($session, $round, $user);
        $this->broadcast($session, $user, 'submission.received');

        return $submission;
    }

    public function finalizeRound(GameSession $session, GameRound $round, User $user, bool $broadcast = true): void
    {
        DB::transaction(function () use ($session, $round) {
            $round = GameRound::whereKey($round->id)->lockForUpdate()->firstOrFail();
            if ($round->status !== 'collecting') {
                return;
            }

            $categories = $session->settings['categories'] ?? self::DEFAULT_CATEGORIES;
            $this->scoring->scoreRound($round, $categories);

            foreach ($round->submissions()->where('is_locked', true)->get() as $submission) {
                GameParticipant::where('game_session_id', $session->id)
                    ->where('user_id', $submission->user_id)
                    ->increment('total_score', (int) $submission->score_total);
            }

            $round->update([
                'status' => 'closed',
                'ended_at' => now(),
                'results_published_at' => now(),
            ]);

            $session->update(['status' => 'waiting', 'last_activity_at' => now()]);
        });

        if ($broadcast) {
            $this->broadcast($session, $user, 'round.finalized');
        }
    }

    public function finish(GameSession $session, User $user): array
    {
        $meta = DB::transaction(function () use ($session) {
            $session = GameSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $finalizedRounds = 0;

            foreach ($session->rounds()->where('status', 'collecting')->get() as $round) {
                $categories = $session->settings['categories'] ?? self::DEFAULT_CATEGORIES;
                $this->scoring->scoreRound($round, $categories);

                foreach ($round->submissions()->where('is_locked', true)->get() as $submission) {
                    GameParticipant::where('game_session_id', $session->id)
                        ->where('user_id', $submission->user_id)
                        ->increment('total_score', (int) $submission->score_total);
                }

                $round->update([
                    'status' => 'closed',
                    'ended_at' => now(),
                    'results_published_at' => now(),
                ]);
                $finalizedRounds++;
            }

            $session->update([
                'status' => 'finished',
                'ended_at' => now(),
                'last_activity_at' => now(),
            ]);

            return [
                'finalized_collecting_rounds' => $finalizedRounds,
                'scored_only_locked' => true,
            ];
        });

        $this->broadcast($session, $user, 'game.finished');

        return $meta;
    }

    public function state(GameSession $session, User $viewer): array
    {
        $this->closeExpiredRound($session, $viewer);

        return $this->stateService->state($session->fresh(), $viewer);
    }

    public function history(GameSession $session, User $viewer, int $page = 1): array
    {
        $this->closeExpiredRound($session, $viewer);

        return $this->stateService->history($session->fresh(), $viewer, $page);
    }

    public function broadcast(GameSession $session, User $viewer, string $type): void
    {
        try {
            broadcast(new GameSessionUpdated(
                (int) $session->room_id,
                (int) $session->id,
                $type,
                $this->state($session->fresh(), $viewer),
            ));
        } catch (\Throwable $e) {
            Log::warning('game.broadcast.failed', [
                'game_session_id' => $session->id,
                'room_id' => $session->room_id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            AppMetrics::increment('external_service_errors_total', ['service' => 'reverb', 'reason' => 'game_broadcast']);
        }
    }

    private function finalizeIfEveryoneSubmitted(GameSession $session, GameRound $round, User $user): void
    {
        $activeCount = GameParticipant::where('game_session_id', $session->id)->where('is_active', true)->count();
        $submittedCount = GameSubmission::where('game_round_id', $round->id)->where('is_locked', true)->count();

        if ($activeCount > 0 && $submittedCount >= $activeCount) {
            $this->finalizeRound($session, $round, $user);
        }
    }

    private function closeExpiredRound(GameSession $session, User $viewer): void
    {
        $round = GameRound::where('game_session_id', $session->id)
            ->where('round_no', $session->current_round_no)
            ->where('status', 'collecting')
            ->first();

        if ($round && $round->submission_deadline && now()->greaterThanOrEqualTo($round->submission_deadline)) {
            $this->finalizeRound($session, $round, $viewer, true);
        }
    }

    private function requireParticipant(GameSession $session, User $user): void
    {
        $exists = GameParticipant::where('game_session_id', $session->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['participant' => 'Önce oyuna katılmalısınız.']);
        }
    }

    private function ensureRoundBelongsToSession(GameSession $session, GameRound $round): void
    {
        if ((int) $round->game_session_id !== (int) $session->id) {
            abort(404);
        }
    }

    private function sanitizeAnswers(GameSession $session, array $answers): array
    {
        $categories = $session->settings['categories'] ?? self::DEFAULT_CATEGORIES;
        $clean = [];
        foreach ($categories as $category) {
            $clean[$category] = trim(mb_substr((string) ($answers[$category] ?? ''), 0, 80));
        }

        return $clean;
    }

    private function applyWaitingSettings(GameSession $session, array $settings): void
    {
        $normalizedSettings = $this->normalizeSettings($settings);

        $session->update([
            'round_time_seconds' => $normalizedSettings['round_time_seconds'],
            'settings' => $normalizedSettings,
            'last_activity_at' => now(),
        ]);
    }

    private function normalizeSettings(array $settings): array
    {
        $categories = $settings['categories'] ?? self::DEFAULT_CATEGORIES;
        $categories = collect($categories)
            ->map(fn ($category) => trim(mb_substr((string) $category, 0, 30)))
            ->filter()
            ->unique(fn ($category) => mb_strtolower($category))
            ->values()
            ->take(10)
            ->all();

        if (count($categories) < 2) {
            throw ValidationException::withMessages(['categories' => 'En az iki kategori seçmelisiniz.']);
        }

        $roundTime = (int) ($settings['round_time_seconds'] ?? self::DEFAULT_ROUND_SECONDS);
        $roundTime = max(30, min(900, $roundTime));

        return [
            'categories' => $categories,
            'round_time_seconds' => $roundTime,
        ];
    }

    private function randomLetter(): string
    {
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'İ', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'Y', 'Z'];

        return $letters[array_rand($letters)];
    }
}
