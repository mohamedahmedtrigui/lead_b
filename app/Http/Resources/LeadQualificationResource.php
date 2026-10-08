<?php

namespace App\Http\Resources;

use App\Models\LeadQualification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeadQualification */
class LeadQualificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $answers = [];
        foreach (LeadQualification::ANSWER_FIELDS as $field) {
            $value = $this->{$field};
            $answers[$field] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        // HH:MM for <input type="time">, ISO 8601 for dates.
        $answers['departure_time'] = $this->departure_time ? substr($this->departure_time, 0, 5) : null;
        $answers['return_time'] = $this->return_time ? substr($this->return_time, 0, 5) : null;
        $answers['callback_at'] = $this->callback_at?->toIso8601String();

        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'dispatcher_id' => $this->dispatcher_id,
            'status' => $this->status->value,
            ...$answers,
            'is_b2b' => $this->is_b2b,
            'interest_score' => $this->interest_score,
            'interest_level' => $this->interest_level?->value,
            'score_breakdown' => $this->score_breakdown ?? [],
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
