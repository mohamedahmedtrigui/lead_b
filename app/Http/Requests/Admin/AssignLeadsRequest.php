<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Manual (re)assignment of one or several leads. dispatcher_id = null unassigns.
 */
class AssignLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'lead_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'lead_ids.*' => ['integer', 'distinct', 'exists:leads,id'],
            'dispatcher_id' => ['present', 'nullable', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
