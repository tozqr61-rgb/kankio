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
                if ($this->isValid($answer, $letter, $category)) {
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
                $valid = $this->isValid($normalized, $letter, $category);
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
        $value = Str::of($value)->trim()->lower()->squish()->toString();

        return strtr($value, [
            'ç' => 'c',
            'ğ' => 'g',
            'ı' => 'i',
            'i̇' => 'i',
            'ö' => 'o',
            'ş' => 's',
            'ü' => 'u',
        ]);
    }

    private function isValid(string $answer, string $letter, string $category): bool
    {
        $letter = $this->normalize($letter);
        if (mb_strlen($answer) < 2 || ! Str::startsWith($answer, $letter)) {
            return false;
        }

        $normalizedCategory = $this->normalize($category);
        $cityCategories = array_map(fn ($value) => $this->normalize((string) $value), config('isimsehir.city_categories', []));
        if (in_array($normalizedCategory, $cityCategories, true)) {
            return in_array($answer, config('isimsehir.cities', []), true);
        }

        return preg_match('/^[a-z0-9\s-]+$/u', $answer) === 1;
    }
}
