<?php

namespace Database\Factories;

use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+216'.fake()->numerify('########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password1'),
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            $user->role ??= UserRole::DISPATCHER;
            $user->status ??= UserStatus::APPROVED;
        });
    }

    public function admin(): static
    {
        return $this->afterMaking(fn (User $user) => $user->role = UserRole::ADMIN);
    }

    public function dispatcher(): static
    {
        return $this->afterMaking(fn (User $user) => $user->role = UserRole::DISPATCHER);
    }

    public function status(UserStatus $status): static
    {
        return $this->afterMaking(fn (User $user) => $user->status = $status);
    }
}
