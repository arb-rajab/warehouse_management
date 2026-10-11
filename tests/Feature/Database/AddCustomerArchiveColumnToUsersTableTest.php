<?php

use Illuminate\Support\Facades\Schema;

test('customer archives are nullable and the additive migration preserves existing archive data on repeat and rollback', function () {
    $customer = createStoreUser();
    expect(Schema::hasColumn('users', 'is_delete'))->toBeTrue()->and($customer->is_delete)->toBeNull();
    $customer->forceFill(['is_delete' => 1])->save();
    $migration = require database_path('migrations/2026_10_08_000001_add_customer_archive_column_to_users_table.php');
    $migration->up();
    $migration->down();
    expect(Schema::hasColumn('users', 'is_delete'))->toBeTrue()->and((bool) $customer->fresh()->is_delete)->toBeTrue();
});
