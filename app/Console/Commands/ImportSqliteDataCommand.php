<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;
use Throwable;

/**
 * Copies this app's own data tables from a standalone legacy SQLite database
 * file (e.g. a VPS backup taken while the app still ran on
 * `DB_CONNECTION=sqlite`) into whichever connection `DB_CONNECTION`/
 * `DB_DATABASE` currently points at — a fresh MySQL database, most commonly.
 *
 * Only tables this app actually owns are copied, in foreign-key-safe order —
 * see TABLES_IN_IMPORT_ORDER. Framework/queue/session/cache housekeeping
 * tables (`wms_cache`, `wms_jobs`, `wms_sessions`, `wms_migrations`,
 * `wms_password_reset_tokens`, `sqlite_sequence`, …) are deliberately never
 * copied: they hold transient runtime state for the *source* environment, not
 * warehouse data, and the destination already has its own from running its
 * migrations.
 */
#[Signature('db:import-sqlite
    {path : Absolute path to the legacy SQLite database file}
    {--truncate : Truncate each destination table before importing}
    {--chunk=500 : Number of rows read from the source and inserted per batch}
    {--force : Force the operation to run when in production}')]
#[Description("Import this app's data from a standalone legacy SQLite database file into the currently configured database connection")]
class ImportSqliteDataCommand extends Command
{
    use ConfirmableTrait;

    private const string SOURCE_CONNECTION = 'sqlite_import_source';

    /**
     * Every table this app owns, in an order that never inserts a row before
     * a row it references by foreign key.
     *
     * @var list<string>
     */
    private const array TABLES_IN_IMPORT_ORDER = [
        'wms_users',
        'wms_roles',
        'wms_permissions',
        'wms_role_has_permissions',
        'wms_model_has_roles',
        'wms_model_has_permissions',
        'wms_personal_access_tokens',
        'products',
        'uploads',
        'wms_product_settings',
        'rows',
        'cells',
        'pallets',
        'cell_status_logs',
        'cell_status_log_flags',
        'cell_verification_rounds',
        'cell_verification_reports',
        'cell_verification_round_row',
        'mobile_app_version_requirements',
    ];

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $path = $this->resolveSourcePath();

        if ($path === null) {
            return self::FAILURE;
        }

        config(['database.connections.'.self::SOURCE_CONNECTION => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        $targetConnection = config('database.default');
        $targetDriver = config("database.connections.{$targetConnection}.driver");

        $this->components->info("Importing '{$path}' into the '{$targetConnection}' connection (driver: {$targetDriver}).");

        $tables = array_values(array_filter(
            self::TABLES_IN_IMPORT_ORDER,
            fn (string $table): bool => Schema::connection(self::SOURCE_CONNECTION)->hasTable($table),
        ));

        foreach (array_diff(self::TABLES_IN_IMPORT_ORDER, $tables) as $table) {
            $this->components->warn("Skipping '{$table}': not present in the source database.");
        }

        if (! $this->assertTablesExistOnTarget($tables, $targetConnection)) {
            return self::FAILURE;
        }

        if (! $this->option('truncate') && ! $this->assertTargetTablesAreEmpty($tables, $targetConnection)) {
            return self::FAILURE;
        }

        try {
            Schema::connection($targetConnection)->withoutForeignKeyConstraints(
                fn () => $this->importTables($tables, $targetConnection, $targetDriver)
            );
        } catch (Throwable $e) {
            $this->components->error("Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info('Import complete.');

        return self::SUCCESS;
    }

    private function resolveSourcePath(): ?string
    {
        $path = $this->argument('path');
        $realPath = realpath($path);

        if ($realPath === false || ! is_file($realPath)) {
            $this->components->error("No SQLite file found at '{$path}'.");

            return null;
        }

        return $realPath;
    }

    /**
     * @param  list<string>  $tables
     */
    private function assertTablesExistOnTarget(array $tables, string $targetConnection): bool
    {
        $missing = array_values(array_filter(
            $tables,
            fn (string $table): bool => ! Schema::connection($targetConnection)->hasTable($table),
        ));

        if ($missing === []) {
            return true;
        }

        $this->components->error(
            'The destination database is missing table(s): '.implode(', ', $missing).
            '. Run migrations against it before importing.'
        );

        return false;
    }

    /**
     * @param  list<string>  $tables
     */
    private function assertTargetTablesAreEmpty(array $tables, string $targetConnection): bool
    {
        $nonEmpty = array_values(array_filter(
            $tables,
            fn (string $table): bool => DB::connection($targetConnection)->table($table)->exists(),
        ));

        if ($nonEmpty === []) {
            return true;
        }

        $this->components->error(
            'These destination tables already have data: '.implode(', ', $nonEmpty).
            '. Re-run with --truncate to overwrite them, or empty the database first.'
        );

        return false;
    }

    /**
     * @param  list<string>  $tables
     */
    private function importTables(array $tables, string $targetConnection, string $targetDriver): void
    {
        if ($this->option('truncate')) {
            foreach (array_reverse($tables) as $table) {
                DB::connection($targetConnection)->table($table)->truncate();
            }
        }

        $chunkSize = max(1, (int) $this->option('chunk'));

        foreach ($tables as $table) {
            $imported = $this->importTable($table, $targetConnection, $targetDriver, $chunkSize);

            $this->components->twoColumnDetail($table, "{$imported} row(s)");
        }
    }

    private function importTable(string $table, string $targetConnection, string $targetDriver, int $chunkSize): int
    {
        $targetColumns = Schema::connection($targetConnection)->getColumnListing($table);
        $sourceColumns = Schema::connection(self::SOURCE_CONNECTION)->getColumnListing($table);
        $orderColumn = $sourceColumns[0];

        $imported = 0;

        DB::connection(self::SOURCE_CONNECTION)->table($table)
            ->orderBy($orderColumn)
            ->chunk($chunkSize, function ($rows) use ($table, $targetConnection, $targetColumns, &$imported): void {
                $mapped = $rows
                    ->map(fn (stdClass $row): array => array_intersect_key((array) $row, array_flip($targetColumns)))
                    ->all();

                DB::connection($targetConnection)->table($table)->insert($mapped);

                $imported += count($mapped);
            });

        if (in_array('id', $targetColumns, true)) {
            $this->resequenceAutoIncrement($table, $targetConnection, $targetDriver);
        }

        return $imported;
    }

    private function resequenceAutoIncrement(string $table, string $targetConnection, string $targetDriver): void
    {
        $max = DB::connection($targetConnection)->table($table)->max('id');

        if ($max === null) {
            return;
        }

        match ($targetDriver) {
            'mysql', 'mariadb' => DB::connection($targetConnection)->statement(
                "ALTER TABLE `{$table}` AUTO_INCREMENT = ".((int) $max + 1)
            ),
            'sqlite' => DB::connection($targetConnection)->table('sqlite_sequence')
                ->updateOrInsert(['name' => $table], ['seq' => (int) $max]),
            'pgsql' => DB::connection($targetConnection)->statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), {$max})"
            ),
            default => null,
        };
    }
}
