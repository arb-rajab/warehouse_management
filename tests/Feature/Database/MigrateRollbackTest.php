<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

test('migrate:rollback leaves no orphan tables and migrate runs cleanly again', function () {
    Artisan::call('migrate:rollback', ['--force' => true]);

    // Only the migration repository itself should remain.
    expect(Schema::getTableListing(schemaQualified: false))->toBe(['wms_migrations']);

    Artisan::call('migrate', ['--force' => true]);

    expect(Schema::hasTable('wms_users'))->toBeTrue();
    // `users` is now a real, permanent table too — the legacy store's own
    // (see .ai/rules/legacy-albaraka-holland.md §3.1), unrelated to
    // `wms_users`, created later by `create_users_table`. Both stay by
    // design; this isn't the leftover pre-rename table.
    expect(Schema::hasTable('users'))->toBeTrue();
});

test('migrate:refresh runs cleanly', function () {
    Artisan::call('migrate:refresh', ['--force' => true]);

    expect(Schema::hasTable('wms_users'))->toBeTrue();
});
