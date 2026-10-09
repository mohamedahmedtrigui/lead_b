<?php

namespace App\Console\Commands;

use App\Domain\Scripts\ScriptService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncScriptCommand extends Command
{
    protected $signature = 'script:sync
        {--reset : Replace the stored wording with the default script}
        {--only=* : With --reset, only these step keys (e.g. --only=route --only=experience)}
        {--connection= : Database connection (default: app default)}';

    protected $description = 'Align the call script stored in the database with the steps defined in code';

    public function handle(ScriptService $scripts): int
    {
        if ($connection = $this->option('connection')) {
            DB::setDefaultConnection($connection);
        }

        $only = $this->option('only');
        $scope = $only ? implode(', ', $only) : 'every step';
        if ($this->option('reset') && ! $this->confirm("Replace the admin wording of {$scope} with the default script?", true)) {
            return self::FAILURE;
        }

        $scripts->syncDefaults((bool) $this->option('reset'), $only);
        $this->info('Call script synchronised'.($this->option('reset') ? ' (default wording restored).' : '.'));

        return self::SUCCESS;
    }
}
