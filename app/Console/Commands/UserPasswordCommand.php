<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Changes a user's password (e.g. the admin) on any connection:
 *   php artisan user:password admin@miraldrive.com --connection=neon
 * The password is asked interactively (hidden), never passed on the command line.
 */
class UserPasswordCommand extends Command
{
    protected $signature = 'user:password {email} {--connection= : Database connection (default: app default)}';

    protected $description = 'Change the password of a user (asked interactively)';

    public function handle(): int
    {
        if ($connection = $this->option('connection')) {
            DB::setDefaultConnection($connection);
        }

        $user = User::where('email', strtolower($this->argument('email')))->first();
        if (! $user) {
            $this->error('No user with this e-mail on connection ['.DB::getDefaultConnection().'].');

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password');
        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => (string) $this->secret('Confirm the password')],
            ['password' => ['required', 'confirmed', Password::defaults()]],
        );
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->info("Password updated for {$user->email} on [".DB::getDefaultConnection().'] (sessions closed).');

        return self::SUCCESS;
    }
}
