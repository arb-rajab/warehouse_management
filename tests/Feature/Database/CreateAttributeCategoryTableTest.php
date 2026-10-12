<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('attribute_category has the expected columns', function () {
    expect(Schema::hasTable('attribute_category'))->toBeTrue();
    expect(Schema::hasColumns('attribute_category', [
        'id', 'category_id', 'attribute_id', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('attribute_category')->insert([
        'category_id' => 1,
        'attribute_id' => 1,
    ]);

    $this->assertDatabaseHas('attribute_category', [
        'category_id' => 1,
        'attribute_id' => 1,
    ]);
});
