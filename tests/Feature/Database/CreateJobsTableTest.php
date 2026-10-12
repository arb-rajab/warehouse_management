<?php

use Illuminate\Support\Facades\Schema;

function loadCreateJobsTableMigration(): object
{
    return require database_path('migrations/0001_01_01_000002_create_jobs_table.php');
}

test('down() drops the wms_ prefixed tables', function () {
    loadCreateJobsTableMigration()->down();

    expect(Schema::hasTable('wms_jobs'))->toBeFalse();
    expect(Schema::hasTable('wms_job_batches'))->toBeFalse();
    expect(Schema::hasTable('wms_failed_jobs'))->toBeFalse();
});

test('down() falls back to the legacy names once a rename migration has un-prefixed them', function () {
    Schema::rename('wms_jobs', 'jobs');
    Schema::rename('wms_job_batches', 'job_batches');
    Schema::rename('wms_failed_jobs', 'failed_jobs');

    loadCreateJobsTableMigration()->down();

    expect(Schema::hasTable('jobs'))->toBeFalse();
    expect(Schema::hasTable('job_batches'))->toBeFalse();
    expect(Schema::hasTable('failed_jobs'))->toBeFalse();
    expect(Schema::hasTable('wms_jobs'))->toBeFalse();
});
