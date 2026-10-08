<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies every business table from one database connection to another,
 * across engines (MySQL <-> PostgreSQL), keeping ids and history.
 *
 *   Initial move to Neon:   php artisan db:copy mysql neon --force
 *   Local offline mirror:   php artisan db:copy neon local_backup --force
 *
 * The target schema must already be migrated (php artisan migrate --database=...).
 * Target tables are emptied first.
 */
class DbCopyCommand extends Command
{
    protected $signature = 'db:copy
        {from : Source connection (e.g. mysql, neon)}
        {to : Target connection (e.g. neon, local_backup)}
        {--force : Do not ask for confirmation (target tables are emptied)}';

    protected $description = 'Copy all business data between two database connections (MySQL <-> PostgreSQL)';

    /** Parents before children (foreign keys). Framework tables (cache, jobs, sessions…) are skipped. */
    private const TABLES = [
        'users',
        'lead_imports',
        'leads',
        'lead_qualifications',
        'call_attempts',
        'dispatcher_notes',
        'lead_assignments',
        'audit_logs',
        'script_steps',
    ];

    /** Self-referencing columns, filled in a second pass. */
    private const DEFERRED = ['users' => ['approved_by']];

    private const CHUNK = 500;

    public function handle(): int
    {
        [$from, $to] = [$this->argument('from'), $this->argument('to')];

        if ($from === $to) {
            $this->error('Source and target must be different connections.');

            return self::FAILURE;
        }

        foreach ([$from, $to] as $connection) {
            if (! config("database.connections.{$connection}")) {
                $this->error("Unknown connection [{$connection}] (see config/database.php).");

                return self::FAILURE;
            }
        }

        $missing = array_diff(
            DB::connection($from)->table('migrations')->pluck('migration')->all(),
            Schema::connection($to)->hasTable('migrations') ? DB::connection($to)->table('migrations')->pluck('migration')->all() : [],
        );
        if ($missing) {
            $this->error("Target [{$to}] is not migrated like [{$from}]. Run: php artisan migrate --database={$to}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Every business table of [{$to}] will be emptied and replaced by [{$from}]. Continue?")) {
            return self::FAILURE;
        }

        $target = DB::connection($to);
        $started = microtime(true);

        $target->transaction(function () use ($from, $to, $target) {
            foreach (array_reverse(self::TABLES) as $table) {
                $target->table($table)->delete();
            }

            foreach (self::TABLES as $table) {
                $count = $this->copyTable($from, $to, $table);
                $this->line(sprintf('  %-22s %6d rows', $table, $count));
            }
        });

        if ($target->getDriverName() === 'pgsql') {
            $this->resetPostgresSequences($to);
        }

        $this->info(sprintf('Copied [%s] -> [%s] in %.1fs.', $from, $to, microtime(true) - $started));

        return self::SUCCESS;
    }

    private function copyTable(string $from, string $to, string $table): int
    {
        $booleans = $this->booleanColumns($to, $table);
        $deferred = self::DEFERRED[$table] ?? [];
        $later = [];
        $count = 0;

        DB::connection($from)->table($table)->orderBy('id')->chunk(self::CHUNK, function ($rows) use ($to, $table, $booleans, $deferred, &$later, &$count) {
            $batch = [];
            foreach ($rows as $row) {
                $row = (array) $row;
                foreach ($booleans as $column) {
                    if (array_key_exists($column, $row) && $row[$column] !== null) {
                        $row[$column] = (bool) $row[$column];
                    }
                }
                foreach ($deferred as $column) {
                    if ($row[$column] !== null) {
                        $later[$row['id']][$column] = $row[$column];
                        $row[$column] = null;
                    }
                }
                $batch[] = $row;
            }
            DB::connection($to)->table($table)->insert($batch);
            $count += count($batch);
        });

        foreach ($later as $id => $values) {
            DB::connection($to)->table($table)->where('id', $id)->update($values);
        }

        return $count;
    }

    /**
     * MySQL stores booleans as TINYINT(1) (0/1); PostgreSQL refuses integers
     * in BOOLEAN columns, so values are cast for those columns.
     *
     * @return array<int, string>
     */
    private function booleanColumns(string $connection, string $table): array
    {
        if (DB::connection($connection)->getDriverName() !== 'pgsql') {
            return [];
        }

        return collect(Schema::connection($connection)->getColumns($table))
            ->filter(fn ($column) => in_array($column['type_name'], ['bool', 'boolean'], true))
            ->pluck('name')
            ->all();
    }

    private function resetPostgresSequences(string $connection): void
    {
        foreach (self::TABLES as $table) {
            DB::connection($connection)->statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1), (SELECT MAX(id) FROM {$table}) IS NOT NULL)"
            );
        }
    }
}
