<?php

namespace App\Domain\Qualification\Services;

use App\Domain\Qualification\Enums\Frequency;
use App\Domain\Qualification\Enums\InterestLevel;
use App\Domain\Qualification\Enums\NextAction;
use App\Domain\Qualification\Enums\PreviousExperience;
use App\Domain\Qualification\Enums\SharedTransport;
use App\Models\LeadQualification;

/**
 * Computes the 0-100 interest score. Weights live in config/qualification.php.
 */
class ScoringService
{
    /**
     * @return array{score: int, level: InterestLevel, breakdown: array<int, array{rule: string, points: int}>}
     */
    public function evaluate(LeadQualification $q): array
    {
        $w = config('qualification.weights');
        $rules = [];

        if ($q->frequency === Frequency::DAILY) {
            $rules['daily_transport'] = $w['daily_transport'];
        }

        $recurringFrequencies = [Frequency::DAILY, Frequency::SEVERAL_TIMES_PER_WEEK, Frequency::FIXED_DAYS];
        if ($q->is_recurring === true || in_array($q->frequency, $recurringFrequencies, true)) {
            $rules['recurring_route'] = $w['recurring_route'];
        }

        $passengers = max((int) $q->passengers_count, $q->detectB2b() ? (int) $q->estimated_passengers_per_trip : 0);
        if ($passengers >= 2) {
            $rules['multiple_passengers'] = $w['multiple_passengers'];
        }

        if ($q->shared_transport === SharedTransport::YES) {
            $rules['accepts_shared'] = $w['accepts_shared'];
        } elseif ($q->shared_transport === SharedTransport::MAYBE) {
            $rules['maybe_shared'] = $w['maybe_shared'];
        }

        if ($q->detectB2b()) {
            $rules['b2b_need'] = $w['b2b_need'];
        }

        if ($q->wants_quotation === true || $q->next_action === NextAction::SEND_QUOTATION) {
            $rules['requests_quotation'] = $w['requests_quotation'];
        }

        if ($q->wants_callback === true || $q->next_action === NextAction::CALLBACK) {
            $rules['requests_callback'] = $w['requests_callback'];
        }

        if ($q->used_miraldrive === PreviousExperience::YES
            && (int) $q->experience_rating >= (int) config('qualification.positive_experience_min_rating')) {
            $rules['positive_experience'] = $w['positive_experience'];
        }

        $score = min((int) config('qualification.max_score'), array_sum($rules));

        return [
            'score' => $score,
            'level' => InterestLevel::fromScore($score),
            'breakdown' => collect($rules)->map(fn ($points, $rule) => ['rule' => $rule, 'points' => $points])->values()->all(),
        ];
    }
}
