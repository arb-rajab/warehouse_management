<?php

use Illuminate\Support\Facades\Schema;

test('the reviews table has the expected columns', function () {
    expect(Schema::hasTable('reviews'))->toBeTrue();
    expect(Schema::hasColumns('reviews', [
        'id', 'product_id', 'user_id', 'rating', 'comment', 'status', 'viewed', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
