<?php

namespace App\Console\Commands;

use App\Domain\Scripts\ScriptService;
use Illuminate\Console\Command;

class SyncScriptCommand extends Command
{
    protected $signature = 'script:sync {--reset : Replace the stored wording with the default script}';

    protected $description = 'Align the call script stored in the database with the steps defined in code';

    public function handle(ScriptService $scripts): int
    {
        if ($this->option('reset') && ! $this->confirm('Replace every admin edit with the default script?', true)) {
            return self::FAILURE;
        }

        $scripts->syncDefaults((bool) $this->option('reset'));
        $this->info('Call script synchronised'.($this->option('reset') ? ' (default wording restored).' : '.'));

        return self::SUCCESS;
    }
}
