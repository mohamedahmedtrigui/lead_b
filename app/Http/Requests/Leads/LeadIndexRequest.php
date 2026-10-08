<?php

namespace App\Http\Requests\Leads;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Qualification\Enums\InterestLevel;
use App\Domain\Qualification\Enums\NextAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadIndexRequest extends FormRequest
{
    public const SORTABLE = ['priority', 'id', 'name', 'status', 'source_created_at', 'last_contacted_at', 'callback_at', 'interest_score', 'priority_stars'];

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(LeadStatus::class)],
            'interest_level' => ['nullable', Rule::enum(InterestLevel::class)],
            'next_action' => ['nullable', Rule::enum(NextAction::class)],
            // Admin only (ignored for dispatchers): "none" = unassigned leads.
            'assigned_to' => ['nullable', 'regex:/^(none|\d+)$/'],
            'channel' => ['nullable', 'string', 'max:50'],
            'callback_due' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Accept ?status=PENDING,NRP as well as ?status[]=PENDING
        if (is_string($this->query('status'))) {
            $this->merge(['status' => array_filter(explode(',', $this->query('status')))]);
        }
    }
}
