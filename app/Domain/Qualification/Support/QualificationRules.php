<?php

namespace App\Domain\Qualification\Support;

use App\Domain\Qualification\Enums\Beneficiary;
use App\Domain\Qualification\Enums\CallAvailability;
use App\Domain\Qualification\Enums\CurrentProvider;
use App\Domain\Qualification\Enums\DecisionRole;
use App\Domain\Qualification\Enums\Frequency;
use App\Domain\Qualification\Enums\NextAction;
use App\Domain\Qualification\Enums\PreviousExperience;
use App\Domain\Qualification\Enums\PurchaseDriver;
use App\Domain\Qualification\Enums\SharedDirection;
use App\Domain\Qualification\Enums\SharedTransport;
use App\Domain\Qualification\Enums\TransportNeed;
use App\Domain\Qualification\Enums\TripType;
use App\Domain\Qualification\Enums\Weekday;
use Illuminate\Validation\Rule;

/**
 * Validation rules of the qualification wizard, shared by the draft-save and
 * completion requests so both stay consistent.
 */
class QualificationRules
{
    /**
     * Type rules only: a draft can be partially filled.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function draft(): array
    {
        $text = fn (int $max = 255) => ['nullable', 'string', "max:{$max}"];
        $int = fn (int $min, int $max) => ['nullable', 'integer', "min:{$min}", "max:{$max}"];
        $enum = fn (string $class) => ['nullable', Rule::enum($class)];
        $time = ['nullable', 'date_format:H:i,H:i:s'];

        return [
            'current_step' => $text(40),
            'call_availability' => $enum(CallAvailability::class),
            'beneficiary' => $enum(Beneficiary::class),
            'beneficiary_details' => $text(),
            'transport_need' => $enum(TransportNeed::class),
            'transport_need_details' => $text(),
            'departure' => $text(),
            'destination' => $text(),
            'trip_type' => $enum(TripType::class),
            'departure_time' => $time,
            'return_time' => $time,
            'days_of_week' => ['nullable', 'array', 'max:7'],
            'days_of_week.*' => ['distinct', Rule::enum(Weekday::class)],
            'frequency' => $enum(Frequency::class),
            'trips_per_week' => $int(0, 100),
            'is_recurring' => ['nullable', 'boolean'],
            // A single vehicle: 4 passengers max (larger groups go through B2B fields).
            'passengers_count' => $int(1, 4),
            'total_employees' => $int(0, 1000000),
            'estimated_passengers_per_trip' => $int(1, 1000),
            'shared_transport' => $enum(SharedTransport::class),
            'shared_direction' => $enum(SharedDirection::class),
            'used_miraldrive' => $enum(PreviousExperience::class),
            'experience_rating' => $int(1, 5),
            'experience_feedback' => $text(2000),
            'improvement_request' => $text(2000),
            'current_provider' => $enum(CurrentProvider::class),
            'current_provider_details' => $text(),
            'customer_preference' => $text(2000),
            'pain_point' => $text(2000),
            'company_name' => $text(),
            'company_size' => $int(0, 1000000),
            'employees_concerned' => $int(0, 1000000),
            'trips_per_day' => $int(0, 1000),
            'b2b_same_schedule' => ['nullable', 'boolean'],
            'decision_maker_name' => $text(),
            'decision_role' => $enum(DecisionRole::class),
            'main_priority' => $enum(PurchaseDriver::class),
            'recap_confirmed' => ['nullable', 'boolean'],
            'wants_quotation' => ['nullable', 'boolean'],
            'wants_callback' => ['nullable', 'boolean'],
            'priority_stars' => $int(1, 5),
            'summary_note' => $text(5000),
            'next_action' => $enum(NextAction::class),
            'callback_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Rules to complete the qualification. When the call ends early
     * (not interested / NRP) only the closing fields are required.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, mixed>>
     */
    public static function complete(array $input): array
    {
        $rules = self::draft();
        $require = function (string $field) use (&$rules) {
            $rules[$field] = ['required', ...array_diff($rules[$field], ['nullable'])];
        };

        $nextAction = NextAction::tryFrom((string) ($input['next_action'] ?? ''));

        $require('next_action');
        $rules['summary_note'] = ['required', 'string', 'min:'.config('qualification.summary_note_min_length'), 'max:5000'];
        $rules['callback_at'] = ['nullable', 'required_if:next_action,'.NextAction::CALLBACK->value, 'date', 'after:now'];

        if ($nextAction?->allowsPartialQualification()) {
            return $rules;
        }

        $isB2b = in_array($input['beneficiary'] ?? null, [Beneficiary::EMPLOYEES->value, Beneficiary::COMPANY->value], true)
            || ($input['transport_need'] ?? null) === TransportNeed::EMPLOYEE->value;

        foreach (['beneficiary', 'transport_need', 'departure', 'destination', 'trip_type', 'frequency',
            'used_miraldrive', 'current_provider', 'priority_stars'] as $field) {
            $require($field);
        }

        $require($isB2b ? 'estimated_passengers_per_trip' : 'passengers_count');

        // The client must have validated the recap ("C'est bien ça ?").
        $rules['recap_confirmed'] = ['required', 'accepted'];

        // Shared transport is only proposed to a single person, outside B2B.
        $sharedApplicable = ! $isB2b && (int) ($input['passengers_count'] ?? 1) <= 1;
        if ($sharedApplicable) {
            $require('shared_transport');
        }

        if (($input['frequency'] ?? null) === Frequency::FIXED_DAYS->value) {
            $rules['days_of_week'] = ['required', 'array', 'min:1', 'max:7'];
        }

        // One-way trips can only be shared on the outbound leg (set automatically).
        if ($sharedApplicable && ($input['shared_transport'] ?? null) === SharedTransport::YES->value
            && ($input['trip_type'] ?? null) === TripType::ROUND_TRIP->value) {
            $require('shared_direction');
        }

        if (($input['used_miraldrive'] ?? null) === PreviousExperience::YES->value) {
            $require('experience_rating');
        }

        if ($isB2b) {
            $require('company_name');
            $require('decision_role');
        }

        return $rules;
    }

    /**
     * French labels used in validation messages.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'beneficiary' => 'bénéficiaire du transport',
            'transport_need' => 'type de déplacement',
            'departure' => 'départ',
            'destination' => 'destination',
            'trip_type' => 'type de trajet',
            'departure_time' => 'heure de départ',
            'return_time' => 'heure de retour',
            'days_of_week' => 'jours de la semaine',
            'frequency' => 'fréquence',
            'trips_per_week' => 'trajets par semaine',
            'passengers_count' => 'nombre de passagers',
            'estimated_passengers_per_trip' => 'passagers par trajet',
            'shared_transport' => 'transport partagé',
            'shared_direction' => 'sens du partage',
            'used_miraldrive' => 'expérience MiralDrive',
            'experience_rating' => 'note de l\'expérience',
            'current_provider' => 'solution actuelle',
            'company_name' => 'nom de l\'entreprise',
            'decision_role' => 'rôle dans la décision',
            'main_priority' => 'priorité principale',
            'recap_confirmed' => 'validation du récapitulatif',
            'priority_stars' => 'priorité (étoiles)',
            'summary_note' => 'résumé de l\'appel',
            'next_action' => 'prochaine action',
            'callback_at' => 'date de rappel',
        ];
    }
}
