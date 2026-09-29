<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the reviews table has the expected columns', function () {
    expect(Schema::hasTable('reviews'))->toBeTrue();
    expect(Schema::hasColumns('reviews', [
        'id', 'product_id', 'user_id', 'rating', 'comment', 'status', 'viewed', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('reviews')->insert([
        'product_id' => 1,
        'user_id' => 1,
        'comment' => 'Great product',
    ]);

    $this->assertDatabaseHas('reviews', [
        'product_id' => 1,
        'comment' => 'Great product',
        'rating' => 0,
        'status' => 1,
        'viewed' => 0,
    ]);
});
