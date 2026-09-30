<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateCustomersNotificationsTableMigration(): object
{
    return require database_path('migrations/2026_09_30_000017_create_customers_notifications_table.php');
}

test('the migration leaves an existing customers_notifications table alone', function () {
    // The store's own database already has this table, with its own data.
    Schema::dropIfExists('customers_notifications');
    Schema::create('customers_notifications', function (Blueprint $table) {
        $table->string('store_only_column')->nullable();
    });

    loadCreateCustomersNotificationsTableMigration()->up();

    expect(Schema::hasColumn('customers_notifications', 'store_only_column'))->toBeTrue()
        ->and(Schema::hasColumn('customers_notifications', 'user_id'))->toBeFalse();
});

test('the migration creates the expected columns', function () {
    expect(Schema::hasColumns('customers_notifications', [
        'id', 'user_id', 'title', 'body', 'status', 'response', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('a customers_notifications row gets the legacy defaults', function () {
    DB::table('customers_notifications')->insert(['user_id' => 1, 'title' => 'Hello', 'body' => 'World']);

    expect((array) DB::table('customers_notifications')->first(['status']))->toEqual([
        'status' => 'pending',
    ]);
});

test('a customer notification is pending, sent or failed', function () {
    expect(fn () => DB::table('customers_notifications')->insert(['user_id' => 1, 'title' => 'Hello', 'body' => 'World', 'status' => 'queued']))
        ->toThrow(QueryException::class);
});

test('the migration skips dropping the customers_notifications table in production', function () {
    app()->instance('env', 'production');
    loadCreateCustomersNotificationsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('customers_notifications'))->toBeTrue();
});
