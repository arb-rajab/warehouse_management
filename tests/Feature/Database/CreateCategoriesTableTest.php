<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('categories has the expected columns', function () {
    expect(Schema::hasTable('categories'))->toBeTrue();
    expect(Schema::hasColumns('categories', [
        'id', 'parent_id', 'level', 'name', 'order_level', 'commision_rate', 'banner', 'icon',
        'featured', 'top', 'digital', 'slug', 'meta_title', 'meta_description', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('categories can be looked up by slug', function () {
    DB::table('categories')->insert([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('categories')->where('slug', 'electronics')->exists())->toBeTrue();
});
