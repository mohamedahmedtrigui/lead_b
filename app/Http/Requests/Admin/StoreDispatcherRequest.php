<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Auth\RegisterRequest;

/**
 * Same fields as the public registration; the account is approved directly.
 */
class StoreDispatcherRequest extends RegisterRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        // Optional automatic allocation of leads to the new account.
        return [...parent::rules(), ...AllocateLeadsRequest::fields()];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'initial_leads' => 'nombre de leads'];
    }
}
