<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('home_categories has the expected columns', function () {
    expect(Schema::hasTable('home_categories'))->toBeTrue();
    expect(Schema::hasColumns('home_categories', [
        'id', 'category_id', 'subsubcategories', 'status', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('home_categories')->insert([
        'category_id' => 1,
    ]);

    $this->assertDatabaseHas('home_categories', [
        'category_id' => 1,
        'status' => 1,
    ]);
});
