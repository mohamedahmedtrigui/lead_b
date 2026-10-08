<?php

namespace App\Http\Resources;

use App\Models\LeadQualification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight qualification used by lead lists (only what tables display).
 *
 * @mixin LeadQualification
 */
class LeadQualificationSummaryResource extends JsonResource
{
    /** Columns to select when eager loading for a list. */
    public const COLUMNS = ['id', 'lead_id', 'status', 'transport_need', 'is_b2b', 'interest_score', 'interest_level', 'priority_stars', 'next_action', 'completed_at'];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'transport_need' => $this->transport_need?->value,
            'is_b2b' => $this->is_b2b,
            'interest_score' => $this->interest_score,
            'interest_level' => $this->interest_level?->value,
            'priority_stars' => $this->priority_stars,
            'next_action' => $this->next_action?->value,
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
