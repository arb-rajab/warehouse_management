<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('customer_product_translations has the expected columns', function () {
    expect(Schema::hasTable('customer_product_translations'))->toBeTrue();
    expect(Schema::hasColumns('customer_product_translations', [
        'id', 'customer_product_id', 'name', 'unit', 'description', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('customer_product_translations')->insert([
        'customer_product_id' => 1,
        'name' => 'Used Bicycle',
        'lang' => 'en',
    ]);

    $this->assertDatabaseHas('customer_product_translations', [
        'customer_product_id' => 1,
        'name' => 'Used Bicycle',
    ]);
});
