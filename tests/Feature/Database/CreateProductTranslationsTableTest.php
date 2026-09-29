<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('product_translations has the expected columns', function () {
    expect(Schema::hasTable('product_translations'))->toBeTrue();
    expect(Schema::hasColumns('product_translations', [
        'id', 'product_id', 'name', 'unit', 'description', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('product_translations')->insert([
        'product_id' => 1,
        'name' => 'Widget',
        'lang' => 'en',
    ]);

    $this->assertDatabaseHas('product_translations', [
        'product_id' => 1,
        'name' => 'Widget',
    ]);
});
