<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lead */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $maxNrp = (int) config('leads.nrp.max_attempts');
        $interval = (int) config('leads.nrp.min_interval_minutes');

        return [
            'id' => $this->id,
            // Source data (read-only, from the CSV import)
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'secondary_phone' => $this->secondary_phone,
            'whatsapp_number' => $this->whatsapp_number,
            'source' => $this->source,
            'form' => $this->form,
            'channel' => $this->channel,
            'stage' => $this->stage,
            'source_owner' => $this->source_owner,
            'labels' => $this->labels,
            'source_created_at' => $this->source_created_at?->toIso8601String(),
            // Pipeline
            'status' => $this->status->value,
            'assigned_to' => $this->assigned_to,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'full_name' => $this->assignee->full_name,
            ] : null),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'nrp' => [
                'attempts' => $this->nrp_attempts,
                'max' => $maxNrp,
                'final' => $this->isNrpFinal(),
                'last_at' => $this->last_nrp_at?->toIso8601String(),
                'next_allowed_at' => $this->last_nrp_at && ! $this->isNrpFinal()
                    ? $this->last_nrp_at->copy()->addMinutes($interval)->toIso8601String()
                    : null,
            ],
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'callback_at' => $this->callback_at?->toIso8601String(),
            'converted_at' => $this->converted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Lists get a compact summary; the detail gets every answer.
            'qualification' => $this->whenLoaded('qualification', fn () => match (true) {
                $this->qualification === null => null,
                $request->routeIs('api.v1.leads.index') => new LeadQualificationSummaryResource($this->qualification),
                default => new LeadQualificationResource($this->qualification),
            }),
            'open_call' => $this->whenLoaded('openCall', fn () => $this->openCall
                ? new CallAttemptResource($this->openCall)
                : null),
        ];
    }
}
