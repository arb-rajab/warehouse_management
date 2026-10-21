<?php

namespace App\Console\Commands;

use App\Support\RequiredFields\RequiredFieldsAudit;
use App\Support\RequiredFields\RequiredFieldsReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

#[Signature('forms:audit-required {--date= : The date in the report file names (default: today)} {--output=.ai : The directory the .md and .json reports are written to}')]
#[Description('Development only, read-only: cross-check which fields the server requires against what each Vue and storefront form requires and marks')]
class AuditRequiredFieldsCommand extends Command
{
    /**
     * Execute the console command. It reads the code and calls each
     * FormRequest's rules() in a rolled-back transaction on a scratch
     * database; it writes only the two report files.
     */
    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('forms:audit-required is a development tool and does not run in production.');

            return self::FAILURE;
        }

        $this->useScratchDatabase();
        $audit = RequiredFieldsAudit::make();
        $result = $audit->run($audit->vueForms(), $audit->bladeForms());
        $date = (string) ($this->option('date') ?: now()->format('Ymd'));
        $report = new RequiredFieldsReport($result, $date);

        $directory = base_path((string) $this->option('output'));
        $base = "{$directory}/required-fields-audit-{$date}";
        file_put_contents("{$base}.md", $report->toMarkdown());
        file_put_contents("{$base}.json", $report->toJson());

        $totals = $report->totals();
        $this->table(['Class', 'Findings'], array_map(fn (string $class, int $count) => [$class, $count], array_keys($totals['by_class']), $totals['by_class']));
        $this->components->info(sprintf(
            '%d Vue and %d Blade forms; %d findings. Wrote %s.md and .json.',
            $totals['forms']['vue'],
            $totals['forms']['blade'],
            array_sum($totals['by_class']),
            ltrim(str_replace(base_path(), '', $base), '/'),
        ));

        return self::SUCCESS;
    }

    /**
     * Some rules() query the database (a language list, a role name). They
     * run against a private in-memory SQLite database, migrated from scratch
     * like the test suite's, so the report never reads or locks a real
     * database and does not depend on its rows. Under the test suite the
     * default connection already is one, migrated by RefreshDatabase, and is
     * left alone (replacing it would break the tests after this one).
     */
    private function useScratchDatabase(): void
    {
        $default = (string) config('database.default');
        if (config("database.connections.{$default}.driver") === 'sqlite' && config("database.connections.{$default}.database") === ':memory:') {
            return;
        }

        foreach (['sqlite', 'telescope', 'pulse', 'health'] as $connection) {
            config(["database.connections.{$connection}.database" => ':memory:', "database.connections.{$connection}.url" => null]);
        }
        config(['database.default' => 'sqlite', 'cache.default' => 'array']);
        DB::purge();
        app(PermissionRegistrar::class)->initializeCache();
        $this->callSilently('migrate', ['--force' => true]);
    }
}
