<?php

namespace App\Models;

use App\Domain\Qualification\Enums\Beneficiary;
use App\Domain\Qualification\Enums\CallAvailability;
use App\Domain\Qualification\Enums\CurrentProvider;
use App\Domain\Qualification\Enums\DecisionRole;
use App\Domain\Qualification\Enums\Frequency;
use App\Domain\Qualification\Enums\InterestLevel;
use App\Domain\Qualification\Enums\NextAction;
use App\Domain\Qualification\Enums\PreviousExperience;
use App\Domain\Qualification\Enums\PurchaseDriver;
use App\Domain\Qualification\Enums\QualificationStatus;
use App\Domain\Qualification\Enums\SharedDirection;
use App\Domain\Qualification\Enums\SharedTransport;
use App\Domain\Qualification\Enums\TransportNeed;
use App\Domain\Qualification\Enums\TripType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadQualification extends Model
{
    /**
     * Answers the dispatcher can write through the wizard. Score, level,
     * status and timestamps are computed server-side and never mass-assigned.
     */
    public const ANSWER_FIELDS = [
        'current_step',
        'call_availability',
        'beneficiary',
        'beneficiary_details',
        'transport_need',
        'transport_need_details',
        'departure',
        'destination',
        'trip_type',
        'departure_time',
        'arrival_time',
        'return_time',
        'extra_routes',
        'days_of_week',
        'frequency',
        'trips_per_week',
        'is_recurring',
        'passengers_count',
        'total_employees',
        'estimated_passengers_per_trip',
        'shared_transport',
        'shared_direction',
        'used_miraldrive',
        'experience_rating',
        'experience_feedback',
        'improvement_request',
        'other_apps_used',
        'other_apps',
        'other_apps_issues',
        'other_apps_feedback',
        'current_provider',
        'current_provider_details',
        'customer_preference',
        'pain_point',
        'company_name',
        'company_size',
        'employees_concerned',
        'trips_per_day',
        'b2b_same_schedule',
        'decision_maker_name',
        'decision_role',
        'main_priority',
        'recap_confirmed',
        'wants_quotation',
        'wants_callback',
        'priority_stars',
        'summary_note',
        'next_action',
        'callback_at',
    ];

    protected $fillable = self::ANSWER_FIELDS;

    protected $attributes = [
        'status' => 'DRAFT',
        'interest_score' => 0,
        'is_b2b' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => QualificationStatus::class,
            'call_availability' => CallAvailability::class,
            'beneficiary' => Beneficiary::class,
            'transport_need' => TransportNeed::class,
            'trip_type' => TripType::class,
            'frequency' => Frequency::class,
            'shared_transport' => SharedTransport::class,
            'shared_direction' => SharedDirection::class,
            'used_miraldrive' => PreviousExperience::class,
            'current_provider' => CurrentProvider::class,
            'decision_role' => DecisionRole::class,
            'main_priority' => PurchaseDriver::class,
            'interest_level' => InterestLevel::class,
            'next_action' => NextAction::class,
            'days_of_week' => 'array',
            'score_breakdown' => 'array',
            'transcript' => 'array',
            'is_recurring' => 'boolean',
            'is_b2b' => 'boolean',
            'b2b_same_schedule' => 'boolean',
            'recap_confirmed' => 'boolean',
            'wants_quotation' => 'boolean',
            'wants_callback' => 'boolean',
            'trips_per_week' => 'integer',
            'passengers_count' => 'integer',
            'total_employees' => 'integer',
            'estimated_passengers_per_trip' => 'integer',
            'experience_rating' => 'integer',
            'extra_routes' => 'array',
            'other_apps_used' => 'boolean',
            'other_apps' => 'array',
            'other_apps_issues' => 'array',
            'company_size' => 'integer',
            'employees_concerned' => 'integer',
            'trips_per_day' => 'integer',
            'interest_score' => 'integer',
            'priority_stars' => 'integer',
            'callback_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatcher_id')->withTrashed();
    }

    /**
     * B2B is derived from who the transport is for / what it is for.
     */
    public function detectB2b(): bool
    {
        return in_array($this->beneficiary, [Beneficiary::EMPLOYEES, Beneficiary::COMPANY], true)
            || $this->transport_need === TransportNeed::EMPLOYEE;
    }

    /**
     * Shared transport is only proposed to a single person, outside B2B.
     */
    public function sharedApplicable(): bool
    {
        return ! $this->detectB2b() && (int) ($this->passengers_count ?? 1) <= 1;
    }

    public function isCompleted(): bool
    {
        return $this->status === QualificationStatus::COMPLETED;
    }
}
