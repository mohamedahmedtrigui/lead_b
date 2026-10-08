<?php

namespace App\Http\Requests\Admin;

use App\Domain\Leads\Services\LeadAssignmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DistributeLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'strategy' => ['required', Rule::in([LeadAssignmentService::STRATEGY_ROUND_ROBIN, LeadAssignmentService::STRATEGY_BALANCED])],
            // Defaults to every active dispatcher.
            'dispatcher_ids' => ['nullable', 'array'],
            'dispatcher_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            // Defaults to every unassigned open lead.
            'lead_ids' => ['nullable', 'array', 'max:5000'],
            'lead_ids.*' => ['integer', 'distinct', 'exists:leads,id'],
        ];
    }
}
