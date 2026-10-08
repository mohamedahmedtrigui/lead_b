<?php

namespace App\Http\Requests\Qualification;

use App\Domain\Qualification\Support\QualificationRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveQualificationRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('work', $this->route('lead'));
    }

    public function rules(): array
    {
        return QualificationRules::draft();
    }

    public function attributes(): array
    {
        return QualificationRules::attributes();
    }
}
