<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateDynamicPopupsTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000009_create_dynamic_popups_table.php');
}

test('the migration leaves an existing dynamic_popups table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('dynamic_popups');
    Schema::create('dynamic_popups', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateDynamicPopupsTableMigration()->up();

    expect(Schema::hasColumn('dynamic_popups', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('dynamic_popups', 'status'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('dynamic_popups', [
        'id', 'status', 'title', 'summary', 'banner', 'btn_link', 'btn_text', 'btn_text_color',
        'btn_background_color', 'delay_seconds', 'offer_id', 'show_page', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('a dynamic_popups row gets the legacy defaults', function () {
    DB::table('dynamic_popups')->insert(['title' => 'Welcome']);

    expect((array) DB::table('dynamic_popups')->first(['status', 'delay_seconds', 'show_page']))->toEqual([
        'status' => 0,
        'delay_seconds' => 10,
        'show_page' => 0,
    ]);
});

test('the migration skips dropping the dynamic_popups table in production', function () {
    app()->instance('env', 'production');
    loadCreateDynamicPopupsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('dynamic_popups'))->toBeTrue();
});
