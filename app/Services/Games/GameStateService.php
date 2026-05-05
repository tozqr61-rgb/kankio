<?php

namespace App\Services\Games;

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
            'rounds' => fn ($q) => $q->orderBy('round_no'),
            'rounds.submissions.user',
        ]);

        $currentRound = $session->rounds->firstWhere('round_no', $session->current_round_no);
        $mySubmission = $currentRound
            ? $currentRound->submissions->firstWhere('user_id', $viewer->id)
            : null;
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
            'history' => $session->rounds
                ->filter(fn ($r) => $currentRound?->id !== $r->id)
                ->values()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'round_no' => $r->round_no,
                    'letter' => $r->letter,
                    'status' => $r->status,
                    'ended_at' => $r->ended_at?->toISOString(),
                    'results_published_at' => $r->results_published_at?->toISOString(),
                    'submissions_count' => $r->submissions->count(),
                    'locked_submissions_count' => $r->submissions->where('is_locked', true)->count(),
                ])
                ->toArray(),
        ];
    }
}
