<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('product_stocks has the expected columns', function () {
    expect(Schema::hasTable('product_stocks'))->toBeTrue();
    expect(Schema::hasColumns('product_stocks', [
        'id', 'product_id', 'variant', 'sku', 'price', 'qty', 'image', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('product_stocks')->insert([
        'product_id' => 1,
        'variant' => 'Size-M-Color-Red',
    ]);

    $this->assertDatabaseHas('product_stocks', [
        'product_id' => 1,
        'variant' => 'Size-M-Color-Red',
        'price' => 0.00,
        'qty' => 0,
    ]);
});
