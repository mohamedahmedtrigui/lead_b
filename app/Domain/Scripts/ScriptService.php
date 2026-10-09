<?php

namespace App\Domain\Scripts;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLogger;
use App\Models\ScriptStep;
use App\Models\User;
use Illuminate\Support\Collection;

class ScriptService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return Collection<int, ScriptStep>
     */
    public function steps(): Collection
    {
        return ScriptStep::query()->orderBy('position')->get();
    }

    /**
     * Aligns the stored script with the code: creates missing steps, removes
     * steps that no longer exist and fixes positions. Admin wording is kept,
     * unless $reset is true (the default wording is then restored), optionally
     * only for the step keys listed in $only.
     *
     * @param  array<int, string>  $only
     */
    public function syncDefaults(bool $reset = false, array $only = []): void
    {
        ScriptStep::query()->whereNotIn('key', DefaultScript::keys())->delete();

        foreach (DefaultScript::steps() as $position => $step) {
            $model = ScriptStep::firstOrNew(['key' => $step['key']]);
            if (! $model->exists || ($reset && ($only === [] || in_array($step['key'], $only, true)))) {
                $model->fill($step);
            }
            $model->position = $position + 1;
            $model->save();
        }
    }

    /**
     * Only wording is editable: prompt keys and option values must stay those
     * defined in code (validated in UpdateScriptStepRequest).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ScriptStep $step, array $data, User $by): ScriptStep
    {
        $default = DefaultScript::find($step->key);

        // Keep only the prompts that still exist in the code (drops removed questions).
        $prompts = array_intersect_key($step->prompts ?? [], $default['prompts']);
        foreach ($data['prompts'] ?? [] as $field => $text) {
            $prompts[$field] = $text;
        }

        $options = $step->options ?? $default['options'];
        foreach ($data['options'] ?? [] as $field => $labels) {
            $options[$field] = collect($default['options'][$field])
                ->map(fn ($opt) => ['value' => $opt['value'], 'label' => $labels[$opt['value']] ?? $opt['label']])
                ->all();
        }

        $responses = $step->responses ?? [];
        foreach ($data['responses'] ?? [] as $field => $replies) {
            $responses[$field] = array_filter($replies, fn ($text) => filled($text));
        }

        $step->fill([
            'responses' => $responses,
            'title' => $data['title'],
            'objective' => $data['objective'] ?? null,
            'script' => $data['script'] ?? null,
            'question' => $data['question'] ?? null,
            'tips' => $data['tips'] ?? null,
            'prompts' => $prompts,
            'options' => $options,
        ]);
        $step->updated_by = $by->id;
        $changed = array_keys($step->getDirty());
        $step->save();

        $this->audit->log(AuditEvent::SCRIPT_UPDATED, null, $step, ['key' => $step->key, 'fields' => $changed], $by);

        return $step;
    }

    public function reset(ScriptStep $step, User $by): ScriptStep
    {
        $step->fill(DefaultScript::find($step->key));
        $step->updated_by = $by->id;
        $step->save();

        $this->audit->log(AuditEvent::SCRIPT_UPDATED, null, $step, ['key' => $step->key, 'reset' => true], $by);

        return $step;
    }
}
