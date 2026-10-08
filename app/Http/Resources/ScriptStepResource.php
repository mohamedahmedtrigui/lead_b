<?php

namespace App\Http\Resources;

use App\Models\ScriptStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ScriptStep */
class ScriptStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'position' => $this->position,
            'title' => $this->title,
            'objective' => $this->objective,
            'script' => $this->script,
            'question' => $this->question,
            'prompts' => (object) ($this->prompts ?? []),
            'options' => (object) ($this->options ?? []),
            'responses' => (object) ($this->responses ?? []),
            'tips' => $this->tips,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'editor' => $this->whenLoaded('editor', fn () => $this->editor?->full_name),
        ];
    }
}
