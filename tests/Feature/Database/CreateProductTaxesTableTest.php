<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('product_taxes has the expected columns', function () {
    expect(Schema::hasTable('product_taxes'))->toBeTrue();
    expect(Schema::hasColumns('product_taxes', [
        'id', 'product_id', 'tax_id', 'tax', 'tax_type', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('product_taxes')->insert([
        'product_id' => 1,
        'tax_id' => 1,
        'tax' => 5.00,
        'tax_type' => 'percent',
    ]);

    $this->assertDatabaseHas('product_taxes', [
        'product_id' => 1,
        'tax' => 5.00,
        'tax_type' => 'percent',
    ]);
});
