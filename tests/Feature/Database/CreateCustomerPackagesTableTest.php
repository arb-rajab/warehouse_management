<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('customer_packages has the expected columns', function () {
    expect(Schema::hasTable('customer_packages'))->toBeTrue();
    expect(Schema::hasColumns('customer_packages', [
        'id', 'name', 'amount', 'product_upload', 'logo', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('customer_packages')->insert([
        'name' => 'Gold Package',
        'amount' => 49.99,
    ]);

    $this->assertDatabaseHas('customer_packages', [
        'name' => 'Gold Package',
        'amount' => 49.99,
    ]);
});
