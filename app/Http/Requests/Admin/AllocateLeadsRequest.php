<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Automatic allocation of N untouched leads to one dispatcher.
 * Also used (optionally) when creating or approving a dispatcher.
 */
class AllocateLeadsRequest extends FormRequest
{
    public const MAX = 500;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return self::fields(required: true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function fields(bool $required = false): array
    {
        return [
            'initial_leads' => [$required ? 'required' : 'nullable', 'integer', 'min:'.($required ? 1 : 0), 'max:'.self::MAX],
            'allow_rebalance' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['initial_leads' => 'nombre de leads'];
    }
}
