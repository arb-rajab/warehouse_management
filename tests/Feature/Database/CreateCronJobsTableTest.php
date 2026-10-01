<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateCronJobsTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000002_create_cron_jobs_table.php');
}

test('the migration leaves an existing cron_jobs table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('cron_jobs');
    Schema::create('cron_jobs', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateCronJobsTableMigration()->up();

    expect(Schema::hasColumn('cron_jobs', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('cron_jobs', 'command'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('cron_jobs', [
        'id', 'command', 'status', 'output', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();
});

test('a cron_jobs row gets the legacy defaults', function () {
    DB::table('cron_jobs')->insert(['command' => 'import:all-products']);

    expect((array) DB::table('cron_jobs')->first(['status']))->toEqual([
        'status' => 'pending',
    ]);
});

test('the migration skips dropping the cron_jobs table in production', function () {
    app()->instance('env', 'production');
    loadCreateCronJobsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('cron_jobs'))->toBeTrue();
});
