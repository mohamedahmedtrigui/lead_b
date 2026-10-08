<?php

namespace Tests;

use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Requests coming from the SPA origin are handled statefully by Sanctum.
     */
    protected function spa(): static
    {
        return $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function dispatcher(UserStatus $status = UserStatus::APPROVED): User
    {
        return User::factory()->dispatcher()->status($status)->create();
    }
}
