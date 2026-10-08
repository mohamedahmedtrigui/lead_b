<?php

namespace App\Http\Requests\Admin;

use App\Domain\Leads\Services\LeadAssignmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.config('leads.import.max_file_size_kb')],
            'distribute' => ['sometimes', 'boolean'],
            'strategy' => ['nullable', Rule::in([LeadAssignmentService::STRATEGY_ROUND_ROBIN, LeadAssignmentService::STRATEGY_BALANCED])],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'fichier CSV'];
    }
}
