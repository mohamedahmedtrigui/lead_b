<?php

namespace App\Http\Requests\Leads;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('addNote', $this->route('lead'));
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return ['body' => 'note'];
    }
}
