<?php

namespace App\Http\Resources;

use App\Models\DispatcherNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DispatcherNote */
class DispatcherNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'type' => $this->type,
            'body' => $this->body,
            'author' => $this->whenLoaded('author', fn () => $this->author ? ['id' => $this->author->id, 'full_name' => $this->author->full_name] : null),
            'call_attempt_id' => $this->call_attempt_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
