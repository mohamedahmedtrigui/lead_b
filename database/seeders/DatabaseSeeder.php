<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            ScriptStepSeeder::class,
        ]);

        // Demo accounts are only created on local environments.
        if (app()->environment('local')) {
            $this->call(DemoDispatcherSeeder::class);
        }
    }
}
