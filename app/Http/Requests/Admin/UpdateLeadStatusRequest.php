<?php

namespace App\Http\Requests\Admin;

use App\Domain\Leads\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'reason' => ['nullable', 'string', 'max:255'],
            // Re-opening an NRP lead can reset its attempt counter.
            'reset_nrp' => ['sometimes', 'boolean'],
        ];
    }
}
