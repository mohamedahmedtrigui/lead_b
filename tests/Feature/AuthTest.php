<?php

namespace Tests\Feature;

use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_pending_dispatcher(): void
    {
        $this->spa()->postJson('/api/v1/auth/register', [
            'first_name' => 'Sara',
            'last_name' => 'Ben Ali',
            'email' => 'Sara@Example.com',
            'phone' => '+216 22 333 444',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertCreated();

        $user = User::where('email', 'sara@example.com')->firstOrFail();
        $this->assertSame(UserStatus::PENDING, $user->status);
        $this->assertTrue($user->isDispatcher());
    }

    public function test_password_only_requires_8_characters(): void
    {
        $payload = fn (string $password) => [
            'first_name' => 'Ali',
            'last_name' => 'Ben Salah',
            'email' => uniqid().'@example.com',
            'phone' => '+21622333444',
            'password' => $password,
            'password_confirmation' => $password,
        ];

        $this->spa()->postJson('/api/v1/auth/register', $payload('123456789'))->assertCreated();
        $this->spa()->postJson('/api/v1/auth/register', $payload('1234567'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_pending_dispatcher_cannot_log_in(): void
    {
        $user = $this->dispatcher(UserStatus::PENDING);

        $this->spa()->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password1'])
            ->assertForbidden()
            ->assertJsonPath('code', 'account_pending');

        $this->assertGuest('web');
    }

    public function test_approved_dispatcher_can_log_in_and_fetch_profile(): void
    {
        $user = $this->dispatcher();

        $this->spa()->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password1'])
            ->assertOk()
            ->assertJsonPath('role', 'DISPATCHER');

        $this->spa()->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->dispatcher();

        $this->spa()->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_deactivated_user_with_live_session_is_blocked(): void
    {
        $user = $this->dispatcher(UserStatus::DEACTIVATED);

        $this->actingAs($user)->getJson('/api/v1/leads')->assertForbidden();
    }

    public function test_dispatcher_cannot_reach_admin_endpoints(): void
    {
        $this->actingAs($this->dispatcher())->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    public function test_admin_approves_a_registration(): void
    {
        $admin = $this->admin();
        $pending = $this->dispatcher(UserStatus::PENDING);

        $this->actingAs($admin)->postJson("/api/v1/admin/dispatchers/{$pending->id}/approve")
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED');
    }
}
