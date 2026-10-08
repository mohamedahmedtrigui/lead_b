<?php

namespace Database\Seeders;

use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local-only demo accounts (password: Dispatch@2026). One pending
 * registration is included to demo the approval workflow.
 */
class DemoDispatcherSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['Sami', 'Dispatcher', 'sami@miraldrive.com', UserStatus::APPROVED],
            ['Ines', 'Dispatcher', 'ines@miraldrive.com', UserStatus::APPROVED],
            ['Youssef', 'Dispatcher', 'youssef@miraldrive.com', UserStatus::APPROVED],
            ['Amira', 'Candidate', 'amira@miraldrive.com', UserStatus::PENDING],
        ];

        foreach ($accounts as [$first, $last, $email, $status]) {
            $user = User::firstOrNew(['email' => $email]);
            if ($user->exists) {
                continue;
            }

            $user->fill([
                'first_name' => $first,
                'last_name' => $last,
                'phone' => '+21620000000',
                'password' => 'Dispatch@2026',
            ]);
            $user->role = UserRole::DISPATCHER;
            $user->status = $status;
            $user->approved_at = $status === UserStatus::APPROVED ? now() : null;
            $user->save();
        }
    }
}
