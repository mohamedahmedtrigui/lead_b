<?php

namespace Database\Seeders;

use App\Domain\Scripts\ScriptService;
use Illuminate\Database\Seeder;

class ScriptStepSeeder extends Seeder
{
    public function run(ScriptService $scripts): void
    {
        $scripts->syncDefaults();
    }
}
