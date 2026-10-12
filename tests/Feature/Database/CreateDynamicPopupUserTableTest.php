<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

function loadCreateDynamicPopupUserTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000010_create_dynamic_popup_user_table.php');
}

test('the migration leaves an existing dynamic_popup_user table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('dynamic_popup_user');
    Schema::create('dynamic_popup_user', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateDynamicPopupUserTableMigration()->up();

    expect(Schema::hasColumn('dynamic_popup_user', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('dynamic_popup_user', 'dynamic_popup_id'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('dynamic_popup_user', [
        'id', 'dynamic_popup_id', 'user_id', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('the migration skips dropping the dynamic_popup_user table in production', function () {
    app()->instance('env', 'production');
    loadCreateDynamicPopupUserTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('dynamic_popup_user'))->toBeTrue();
});
