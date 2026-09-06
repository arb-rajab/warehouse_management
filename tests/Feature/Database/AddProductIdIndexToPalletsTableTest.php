<?php

use Illuminate\Support\Facades\Schema;

test('pallets.product_id has an index', function () {
    $indexes = collect(Schema::getIndexes('pallets'));

    expect($indexes->contains(fn (array $index) => $index['columns'] === ['product_id']))->toBeTrue();
});
