<?php

namespace App\Http\Resources;

use App\Models\LeadAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeadAssignment */
class LeadAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $name = fn ($user) => $user ? ['id' => $user->id, 'full_name' => $user->full_name] : null;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'reason' => $this->reason,
            'from' => $this->whenLoaded('fromUser', fn () => $name($this->fromUser)),
            'to' => $this->whenLoaded('toUser', fn () => $name($this->toUser)),
            'by' => $this->whenLoaded('assigner', fn () => $name($this->assigner)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
