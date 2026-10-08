<?php

namespace App\Console\Commands;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadAssignmentService;
use App\Domain\Leads\Services\LeadImportService;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Console\Command;

class ImportLeadsCommand extends Command
{
    protected $signature = 'leads:import
        {path : Path to the CSV file}
        {--distribute : Distribute unassigned leads between active dispatchers}
        {--strategy=round_robin : round_robin or balanced}';

    protected $description = 'Import leads from a CSV export (source fields only, qualification data is never overwritten)';

    public function handle(LeadImportService $importer, LeadAssignmentService $assignments): int
    {
        $path = $this->argument('path');
        if (! is_readable($path)) {
            $this->error("File not readable: {$path}");

            return self::FAILURE;
        }

        try {
            $import = $importer->import($path, basename($path));
        } catch (BusinessRuleException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Rows', 'Created', 'Updated', 'Skipped'], [[
            $import->total_rows, $import->created_count, $import->updated_count, $import->skipped_count,
        ]]);

        if ($this->option('distribute')) {
            $leads = Lead::query()->whereNull('assigned_to')->whereIn('status', LeadStatus::open())
                ->orderBy('source_created_at')->orderBy('id')->get();
            $dispatchers = User::query()->activeDispatchers()->get();

            if ($dispatchers->isEmpty()) {
                $this->warn('No active dispatcher: leads left unassigned.');

                return self::SUCCESS;
            }

            $received = $assignments->distribute($leads, $dispatchers, null, $this->option('strategy'));
            $this->table(['Dispatcher', 'Leads received'], $dispatchers->map(fn (User $u) => [$u->full_name, $received[$u->id] ?? 0]));
        }

        return self::SUCCESS;
    }
}
