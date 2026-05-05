<?php

namespace App\Services\Games;

use App\Models\GameRound;
use App\Models\GameSession;
use App\Models\User;

class GameStateService
{
    public function state(GameSession $session, User $viewer): array
    {
        $session->load([
            'room',
            'creator',
            'participants.user',
        ]);

        $currentRound = GameRound::with('submissions.user')
            ->where('game_session_id', $session->id)
            ->where('round_no', $session->current_round_no)
            ->first();
        $mySubmission = $currentRound
            ? $currentRound->submissions->firstWhere('user_id', $viewer->id)
            : null;
        $historyRounds = GameRound::where('game_session_id', $session->id)
            ->when($currentRound, fn ($q) => $q->whereKeyNot($currentRound->id))
            ->withCount([
                'submissions',
                'submissions as locked_submissions_count' => fn ($q) => $q->where('is_locked', true),
            ])
            ->orderByDesc('round_no')
            ->limit(10)
            ->get()
            ->reverse()
            ->values();
        $categories = $session->settings['categories'] ?? IsimSehirGameService::DEFAULT_CATEGORIES;

        $participants = $session->participants
            ->sortByDesc('total_score')
            ->values();
        $leader = $participants->first();

        return [
            'now' => now()->toISOString(),
            'session' => [
                'id' => $session->id,
                'room_id' => $session->room_id,
                'game_type' => $session->game_type,
                'status' => $session->status,
                'current_round_no' => $session->current_round_no,
                'round_time_seconds' => $session->round_time_seconds,
                'settings' => $session->settings,
                'created_by' => $session->created_by,
            ],
            'categories' => $categories,
            'leader' => $leader ? [
                'user_id' => $leader->user_id,
                'username' => $leader->user?->username,
                'total_score' => (int) $leader->total_score,
            ] : null,
            'participants' => $participants
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'user_id' => $p->user_id,
                    'username' => $p->user?->username,
                    'avatar_url' => $p->user?->avatar_url,
                    'is_active' => (bool) $p->is_active,
                    'is_ready' => (bool) $p->is_ready,
                    'total_score' => (int) $p->total_score,
                ])
                ->toArray(),
            'round' => $currentRound ? [
                'id' => $currentRound->id,
                'round_no' => $currentRound->round_no,
                'letter' => $currentRound->letter,
                'status' => $currentRound->status,
                'started_at' => $currentRound->started_at?->toISOString(),
                'submission_deadline' => $currentRound->submission_deadline?->toISOString(),
                'ended_at' => $currentRound->ended_at?->toISOString(),
                'results_published_at' => $currentRound->results_published_at?->toISOString(),
                'submissions' => $currentRound->submissions->map(fn ($s) => [
                    'user_id' => $s->user_id,
                    'username' => $s->user?->username,
                    'answers' => $currentRound->status === 'collecting' && $s->user_id !== $viewer->id ? [] : ($s->answers ?? []),
                    'submitted_at' => $s->submitted_at?->toISOString(),
                    'is_locked' => (bool) $s->is_locked,
                    'score_total' => (int) $s->score_total,
                    'score_breakdown' => $currentRound->status === 'collecting' ? [] : ($s->score_breakdown ?? []),
                ])->toArray(),
            ] : null,
            'my_submission' => $mySubmission ? [
                'answers' => $mySubmission->answers ?? [],
                'submitted_at' => $mySubmission->submitted_at?->toISOString(),
                'is_locked' => (bool) $mySubmission->is_locked,
                'score_total' => (int) $mySubmission->score_total,
                'score_breakdown' => $mySubmission->score_breakdown ?? [],
            ] : null,
            'history' => $historyRounds
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'round_no' => $r->round_no,
                    'letter' => $r->letter,
                    'status' => $r->status,
                    'ended_at' => $r->ended_at?->toISOString(),
                    'results_published_at' => $r->results_published_at?->toISOString(),
                    'submissions_count' => (int) $r->submissions_count,
                    'locked_submissions_count' => (int) $r->locked_submissions_count,
                ])
                ->toArray(),
        ];
    }

    public function history(GameSession $session, User $viewer, int $page = 1): array
    {
        $rounds = GameRound::where('game_session_id', $session->id)
            ->with(['submissions.user'])
            ->withCount([
                'submissions',
                'submissions as locked_submissions_count' => fn ($q) => $q->where('is_locked', true),
            ])
            ->orderByDesc('round_no')
            ->paginate(10, ['*'], 'page', max(1, $page));

        return [
            'now' => now()->toISOString(),
            'rounds' => collect($rounds->items())->map(fn ($round) => [
                'id' => $round->id,
                'round_no' => $round->round_no,
                'letter' => $round->letter,
                'status' => $round->status,
                'started_at' => $round->started_at?->toISOString(),
                'submission_deadline' => $round->submission_deadline?->toISOString(),
                'ended_at' => $round->ended_at?->toISOString(),
                'results_published_at' => $round->results_published_at?->toISOString(),
                'submissions_count' => (int) $round->submissions_count,
                'locked_submissions_count' => (int) $round->locked_submissions_count,
                'submissions' => $round->submissions->map(fn ($submission) => [
                    'user_id' => $submission->user_id,
                    'username' => $submission->user?->username,
                    'answers' => $round->status === 'collecting' && $submission->user_id !== $viewer->id ? [] : ($submission->answers ?? []),
                    'submitted_at' => $submission->submitted_at?->toISOString(),
                    'is_locked' => (bool) $submission->is_locked,
                    'score_total' => (int) $submission->score_total,
                    'score_breakdown' => $round->status === 'collecting' ? [] : ($submission->score_breakdown ?? []),
                ])->values()->toArray(),
            ])->values()->toArray(),
            'pagination' => [
                'current_page' => $rounds->currentPage(),
                'has_more' => $rounds->hasMorePages(),
                'total' => $rounds->total(),
            ],
        ];
    }
}
