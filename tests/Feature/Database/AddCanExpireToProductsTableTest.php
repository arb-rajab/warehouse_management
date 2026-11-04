<?php

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadAddCanExpireMigration(): object
{
    return require database_path('migrations/2026_11_03_100000_add_can_expire_to_products_table.php');
}

test('products.can_expire is a NOT NULL boolean that defaults to false', function () {
    $column = collect(Schema::getColumns('products'))->firstWhere('name', 'can_expire');

    expect($column)->not->toBeNull()
        ->and($column['nullable'])->toBeFalse();

    $product = Product::factory()->create();
    $noise = Product::factory()->create();
    DB::table('products')->where('id', $noise->id)->update(['can_expire' => true]);

    expect((bool) DB::table('products')->where('id', $product->id)->value('can_expire'))->toBeFalse()
        ->and((bool) DB::table('products')->where('id', $noise->id)->value('can_expire'))->toBeTrue()
        ->and($product->fresh()->can_expire)->toBeFalse()
        ->and($noise->fresh()->can_expire)->toBeTrue();
});

test('the addition is skipped when the column already exists and the rows are kept', function () {
    $product = Product::factory()->create(['name' => 'Perishable']);
    DB::table('products')->where('id', $product->id)->update(['can_expire' => true]);

    loadAddCanExpireMigration()->up();

    expect(Schema::hasColumn('products', 'can_expire'))->toBeTrue()
        ->and((bool) DB::table('products')->where('id', $product->id)->value('can_expire'))->toBeTrue();
});

test('rolling back drops the column, migrating again restores it as false, and rolling back twice is harmless', function () {
    $product = Product::factory()->create();
    $migration = loadAddCanExpireMigration();

    $migration->down();

    try {
        expect(Schema::hasColumn('products', 'can_expire'))->toBeFalse();
        $migration->down();
        expect(Schema::hasColumn('products', 'can_expire'))->toBeFalse();
    } finally {
        $migration->up();
    }

    expect(Schema::hasColumn('products', 'can_expire'))->toBeTrue()
        ->and((bool) DB::table('products')->where('id', $product->id)->value('can_expire'))->toBeFalse();
});
