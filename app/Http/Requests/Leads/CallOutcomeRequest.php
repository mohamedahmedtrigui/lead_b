<?php

namespace App\Http\Requests\Leads;

use App\Domain\Calls\Enums\CallOutcome;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CallOutcomeRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('work', $this->route('lead'));
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in([...CallOutcome::quickOutcomeValues(), CallOutcome::CONNECTED->value])],
            'callback_at' => ['nullable', 'required_if:outcome,'.CallOutcome::CALLBACK_REQUESTED->value, 'date', 'after:now'],
            'note' => [
                'nullable',
                Rule::requiredIf(fn () => in_array($this->input('outcome'), [CallOutcome::NOT_INTERESTED->value, CallOutcome::INVALID_NUMBER->value], true)),
                'string',
                'max:2000',
            ],
        ];
    }

    public function attributes(): array
    {
        return ['callback_at' => 'date de rappel', 'note' => 'note'];
    }
}
