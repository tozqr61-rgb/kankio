<?php

namespace App\Services\Games;

use App\Models\GameRound;
use Illuminate\Support\Str;

class GameScoringService
{
    public function scoreRound(GameRound $round, array $categories, bool $onlyLocked = true): void
    {
        $round->load('submissions');
        $letter = Str::lower($round->letter);
        $scorableSubmissions = $onlyLocked
            ? $round->submissions->where('is_locked', true)
            : $round->submissions;
        $normalizedByCategory = [];

        foreach ($categories as $category) {
            $answers = [];
            foreach ($scorableSubmissions as $submission) {
                $answer = $this->normalize((string) ($submission->answers[$category] ?? ''));
                if ($this->isValid($answer, $letter)) {
                    $answers[] = $answer;
                }
            }
            $normalizedByCategory[$category] = array_count_values($answers);
        }

        foreach ($round->submissions as $submission) {
            if ($onlyLocked && ! $submission->is_locked) {
                $submission->update([
                    'score_total' => 0,
                    'score_breakdown' => [],
                ]);
                continue;
            }

            $breakdown = [];
            $total = 0;

            foreach ($categories as $category) {
                $raw = (string) ($submission->answers[$category] ?? '');
                $normalized = $this->normalize($raw);
                $valid = $this->isValid($normalized, $letter);
                $duplicate = $valid && (($normalizedByCategory[$category][$normalized] ?? 0) > 1);
                $score = ! $valid ? 0 : ($duplicate ? 5 : 10);

                $breakdown[$category] = [
                    'answer' => $raw,
                    'valid' => $valid,
                    'duplicate' => $duplicate,
                    'score' => $score,
                ];
                $total += $score;
            }

            $submission->update([
                'score_total' => $total,
                'score_breakdown' => $breakdown,
            ]);
        }
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->trim()->lower()->squish()->toString();
    }

    private function isValid(string $answer, string $letter): bool
    {
        return mb_strlen($answer) >= 2 && Str::startsWith($answer, $letter);
    }
}
