<?php

use Illuminate\Support\Facades\Schema;

function loadAddIndexesToStoreTablesMigration(): object
{
    return require database_path('migrations/2026_10_03_000005_add_indexes_to_store_tables.php');
}

/**
 * @param  list<string>  $columns
 */
function tableHasIndexOn(string $table, array $columns): bool
{
    return collect(Schema::getIndexes($table))->contains(fn (array $index) => $index['columns'] === $columns);
}

/**
 * @param  list<string>  $columns
 * @return list<array<string, mixed>>
 */
function indexesOn(string $table, array $columns): array
{
    return collect(Schema::getIndexes($table))
        ->filter(fn (array $index) => $index['columns'] === $columns)
        ->values()
        ->all();
}

test('every store lookup column is indexed', function (string $table, array $columns) {
    expect(tableHasIndexOn($table, $columns))->toBeTrue();
})->with([
    ['orders', ['user_id']],
    ['orders', ['seller_id']],
    ['orders', ['combined_order_id']],
    ['orders', ['delivery_status']],
    ['orders', ['payment_status']],
    ['carts', ['user_id']],
    ['carts', ['product_id']],
    ['carts', ['temp_user_id']],
    ['product_stocks', ['product_id']],
    ['order_details', ['order_id']],
    ['order_details', ['seller_id']],
    ['order_details', ['product_id']],
    ['trips', ['driver_id']],
    ['trips', ['truck_id']],
    ['visits', ['rep_id']],
    ['visits', ['customer_id']],
    ['visits', ['order_id']],
    ['checkpoints', ['trip_id']],
    ['checkpoints', ['relationable_type', 'relationable_id']],
]);

test('running up() again is a no-op', function () {
    loadAddIndexesToStoreTablesMigration()->up();

    expect(indexesOn('orders', ['user_id']))->toHaveCount(1)
        ->and(indexesOn('checkpoints', ['relationable_type', 'relationable_id']))->toHaveCount(1);
});

test('a column the store already indexes under its own name gets no duplicate', function () {
    Schema::dropIfExists('product_stocks');
    Schema::create('product_stocks', function ($table) {
        $table->integer('product_id');
        $table->index('product_id', 'store_product_stock_idx');
    });

    loadAddIndexesToStoreTablesMigration()->up();

    expect(indexesOn('product_stocks', ['product_id']))->toHaveCount(1)
        ->and(Schema::hasIndex('product_stocks', 'store_product_stock_idx'))->toBeTrue();
});

test('a table or column the store does not have is skipped', function () {
    Schema::dropIfExists('visits');
    Schema::dropIfExists('carts');
    Schema::create('carts', function ($table) {
        $table->integer('user_id')->nullable();
    });

    loadAddIndexesToStoreTablesMigration()->up();

    expect(Schema::hasTable('visits'))->toBeFalse()
        ->and(tableHasIndexOn('carts', ['user_id']))->toBeTrue()
        ->and(Schema::hasColumn('carts', 'product_id'))->toBeFalse();
});

test('down() drops the indexes outside production but not in production', function () {
    $migration = loadAddIndexesToStoreTablesMigration();

    app()->instance('env', 'production');
    $migration->down();
    app()->instance('env', 'testing');

    expect(tableHasIndexOn('orders', ['user_id']))->toBeTrue();

    $migration->down();

    expect(tableHasIndexOn('orders', ['user_id']))->toBeFalse()
        ->and(tableHasIndexOn('checkpoints', ['trip_id']))->toBeFalse();
});
