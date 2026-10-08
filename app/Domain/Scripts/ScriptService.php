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
     * Creates missing steps from the defaults (keeps admin edits untouched).
     */
    public function syncDefaults(): void
    {
        foreach (DefaultScript::steps() as $position => $step) {
            $model = ScriptStep::firstOrNew(['key' => $step['key']]);
            $model->position = $position + 1;
            if (! $model->exists) {
                $model->fill($step);
            }
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

        $prompts = $step->prompts ?? [];
        foreach ($data['prompts'] ?? [] as $field => $text) {
            $prompts[$field] = $text;
        }

        $options = $step->options ?? $default['options'];
        foreach ($data['options'] ?? [] as $field => $labels) {
            $options[$field] = collect($default['options'][$field])
                ->map(fn ($opt) => ['value' => $opt['value'], 'label' => $labels[$opt['value']] ?? $opt['label']])
                ->all();
        }

        $step->fill([
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
