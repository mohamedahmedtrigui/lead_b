<?php

namespace App\Http\Resources;

use App\Models\CallAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CallAttempt */
class CallAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'lead' => $this->whenLoaded('lead', fn () => ['id' => $this->lead->id, 'name' => $this->lead->name, 'phone' => $this->lead->phone]),
            'dispatcher_id' => $this->dispatcher_id,
            'dispatcher' => $this->whenLoaded('dispatcher', fn () => ['id' => $this->dispatcher->id, 'full_name' => $this->dispatcher->full_name]),
            'attempt_number' => $this->attempt_number,
            'outcome' => $this->outcome?->value,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'duration_seconds' => $this->duration_seconds,
            'note' => $this->note,
        ];
    }
}
