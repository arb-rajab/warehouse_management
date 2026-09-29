<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('customer_package_translations has the expected columns', function () {
    expect(Schema::hasTable('customer_package_translations'))->toBeTrue();
    expect(Schema::hasColumns('customer_package_translations', [
        'id', 'customer_package_id', 'name', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('customer_package_translations')->insert([
        'customer_package_id' => 1,
        'name' => 'Gold Package',
        'lang' => 'en',
    ]);

    $this->assertDatabaseHas('customer_package_translations', [
        'customer_package_id' => 1,
        'name' => 'Gold Package',
    ]);
});
