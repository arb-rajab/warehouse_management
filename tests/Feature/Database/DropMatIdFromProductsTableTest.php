<?php

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadDropMatIdMigration(): object
{
    return require database_path('migrations/2026_11_02_100000_drop_mat_id_from_products_table.php');
}

test('products has no mat_id column after migrating', function () {
    expect(Schema::hasColumn('products', 'mat_id'))->toBeFalse()
        ->and(Schema::hasColumn('products', 'id'))->toBeTrue();
});

test('the drop is skipped when the column is already gone', function () {
    $product = Product::factory()->create(['name' => 'Kept product']);

    loadDropMatIdMigration()->up();

    expect(Schema::hasColumn('products', 'mat_id'))->toBeFalse()
        ->and(DB::table('products')->where('id', $product->id)->value('name'))->toBe('Kept product');
});

test('rolling back restores a nullable string column filled from id, and migrating again drops it with the rows kept', function () {
    $product = Product::factory()->create(['name' => 'Rolled back product']);
    $noise = Product::factory()->create(['name' => 'Noise product']);
    $migration = loadDropMatIdMigration();

    $migration->down();

    try {
        $column = collect(Schema::getColumns('products'))->firstWhere('name', 'mat_id');
        expect($column)->not->toBeNull()
            ->and($column['nullable'])->toBeTrue()
            ->and(DB::table('products')->where('id', $product->id)->value('mat_id'))->toBe((string) $product->id)
            ->and(DB::table('products')->where('id', $noise->id)->value('mat_id'))->toBe((string) $noise->id);

        DB::table('products')->where('id', $noise->id)->update(['mat_id' => null]);
        expect(DB::table('products')->where('id', $noise->id)->value('mat_id'))->toBeNull();
    } finally {
        $migration->up();
    }

    expect(Schema::hasColumn('products', 'mat_id'))->toBeFalse()
        ->and(DB::table('products')->whereIn('id', [$product->id, $noise->id])->count())->toBe(2);
});

test('rolling back twice leaves the restored column untouched', function () {
    $migration = loadDropMatIdMigration();

    $migration->down();

    try {
        $migration->down();
        expect(Schema::hasColumn('products', 'mat_id'))->toBeTrue();
    } finally {
        $migration->up();
    }
});
