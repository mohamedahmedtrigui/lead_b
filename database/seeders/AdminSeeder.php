<?php

namespace Database\Seeders;

use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates the initial administrator from ADMIN_* environment variables.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower((string) env('ADMIN_EMAIL'));
        $password = (string) env('ADMIN_PASSWORD');

        if ($email === '' || $password === '') {
            throw new RuntimeException('ADMIN_EMAIL and ADMIN_PASSWORD must be set in .env to seed the administrator.');
        }

        $admin = User::firstOrNew(['email' => $email]);
        if (! $admin->exists) {
            $admin->fill([
                'first_name' => env('ADMIN_FIRST_NAME', 'Admin'),
                'last_name' => env('ADMIN_LAST_NAME', 'MiralDrive'),
                'password' => $password,
            ]);
        }
        $admin->role = UserRole::ADMIN;
        $admin->status = UserStatus::APPROVED;
        $admin->approved_at ??= now();
        $admin->save();
    }
}
