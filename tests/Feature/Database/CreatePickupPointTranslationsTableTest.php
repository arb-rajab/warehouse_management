<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('pickup_point_translations has the expected columns', function () {
    expect(Schema::hasTable('pickup_point_translations'))->toBeTrue();
    expect(Schema::hasColumns('pickup_point_translations', [
        'id', 'pickup_point_id', 'name', 'address', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('pickup_point_translations')->insert([
        'pickup_point_id' => 1,
        'name' => 'Main Warehouse',
        'address' => '123 Main St',
        'lang' => 'en',
    ]);

    $this->assertDatabaseHas('pickup_point_translations', [
        'pickup_point_id' => 1,
        'name' => 'Main Warehouse',
    ]);
});
