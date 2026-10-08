<?php

namespace App\Http\Requests\Admin;

use App\Domain\Scripts\DefaultScript;
use App\Models\ScriptStep;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admins can rewrite the wording, but not add/remove fields or option values
 * (those are bound to the database columns).
 */
class UpdateScriptStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        /** @var ScriptStep $step */
        $step = $this->route('scriptStep');
        $default = DefaultScript::find($step->key) ?? ['prompts' => [], 'options' => []];

        $promptKeys = array_keys($default['prompts']);
        $optionFields = array_keys($default['options']);

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'objective' => ['nullable', 'string', 'max:255'],
            'script' => ['nullable', 'string', 'max:5000'],
            'question' => ['nullable', 'string', 'max:1000'],
            'tips' => ['nullable', 'string', 'max:2000'],
            'prompts' => $promptKeys ? ['nullable', 'array:'.implode(',', $promptKeys)] : ['nullable', 'array', 'max:0'],
            'prompts.*' => ['nullable', 'string', 'max:1000'],
            'options' => $optionFields ? ['nullable', 'array:'.implode(',', $optionFields)] : ['nullable', 'array', 'max:0'],
        ];

        // options.{field} = { VALUE: "label", ... } and
        // responses.{field} = { VALUE: "reply" }, restricted to known values.
        $rules['responses'] = $optionFields ? ['nullable', 'array:'.implode(',', $optionFields)] : ['nullable', 'array', 'max:0'];
        foreach ($default['options'] as $field => $options) {
            $values = implode(',', array_column($options, 'value'));
            $rules["options.{$field}"] = ['nullable', 'array:'.$values];
            $rules["options.{$field}.*"] = ['required', 'string', 'max:120'];
            $rules["responses.{$field}"] = ['nullable', 'array:'.$values];
            $rules["responses.{$field}.*"] = ['nullable', 'string', 'max:1000'];
        }

        return $rules;
    }
}
