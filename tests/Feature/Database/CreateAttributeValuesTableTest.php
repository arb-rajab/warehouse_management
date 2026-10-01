<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('attribute_values has the expected columns', function () {
    expect(Schema::hasTable('attribute_values'))->toBeTrue();
    expect(Schema::hasColumns('attribute_values', [
        'id', 'attribute_id', 'value', 'color_code', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('attribute_values')->insert([
        'attribute_id' => 1,
        'value' => 'Large',
        'color_code' => '#ffffff',
    ]);

    $this->assertDatabaseHas('attribute_values', [
        'attribute_id' => 1,
        'value' => 'Large',
    ]);
});
