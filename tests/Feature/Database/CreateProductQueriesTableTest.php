<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('product_queries has the expected columns', function () {
    expect(Schema::hasTable('product_queries'))->toBeTrue();
    expect(Schema::hasColumns('product_queries', [
        'id', 'customer_id', 'seller_id', 'product_id', 'question', 'reply', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('product_queries')->insert([
        'customer_id' => 1,
        'seller_id' => 1,
        'product_id' => 1,
        'question' => 'Is this still in stock?',
    ]);

    $this->assertDatabaseHas('product_queries', [
        'customer_id' => 1,
        'product_id' => 1,
        'question' => 'Is this still in stock?',
    ]);
});
