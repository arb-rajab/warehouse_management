<?php

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the origin migration preserves existing rows and classifies them conservatively', function () {
    $product = Product::factory()->create(['name' => 'Pre-marker product']);
    $before = $product->fresh()->getRawOriginal();
    $migration = require database_path('migrations/2026_10_22_101723_add_synced_at_to_products_table.php');
    $migration->down();
    try {
        $migration->up();
        $after = DB::table('products')->where('id', $product->id)->first();
        expect($after->synced_at)->toBeNull();
        foreach ($before as $column => $value) {
            expect($after->{$column})->toBe($value, $column);
        }
        $column = collect(Schema::getColumns('products'))->firstWhere('name', 'synced_at');
        expect($column['nullable'])->toBeTrue();
        DB::table('products')->where('id', $product->id)->update(['synced_at' => '2026-10-22 10:00:00']);
        expect(DB::table('products')->where('id', $product->id)->value('synced_at'))->toBe('2026-10-22 10:00:00');
    } finally {
        if (! Schema::hasColumn('products', 'synced_at')) {
            $migration->up();
        }
        $product->delete();
    }
});
