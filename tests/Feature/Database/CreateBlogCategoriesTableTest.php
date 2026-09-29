<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('blog_categories has the expected columns', function () {
    expect(Schema::hasTable('blog_categories'))->toBeTrue();
    expect(Schema::hasColumns('blog_categories', [
        'id', 'category_name', 'slug', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();

    DB::table('blog_categories')->insert([
        'category_name' => 'News',
        'slug' => 'news',
    ]);

    $this->assertDatabaseHas('blog_categories', [
        'slug' => 'news',
    ]);
});
