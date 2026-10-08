<?php

namespace App\Http\Requests\Qualification;

use App\Domain\Qualification\Support\QualificationRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CompleteQualificationRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('work', $this->route('lead'));
    }

    public function rules(): array
    {
        return QualificationRules::complete($this->all());
    }

    public function attributes(): array
    {
        return QualificationRules::attributes();
    }
}
