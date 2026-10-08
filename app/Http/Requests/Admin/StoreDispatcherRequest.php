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
}
