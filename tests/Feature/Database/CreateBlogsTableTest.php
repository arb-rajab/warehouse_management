<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('blogs has the expected columns', function () {
    expect(Schema::hasTable('blogs'))->toBeTrue();
    expect(Schema::hasColumns('blogs', [
        'id', 'category_id', 'title', 'slug', 'short_description', 'description',
        'banner', 'meta_title', 'meta_img', 'meta_description', 'meta_keywords',
        'status', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();

    DB::table('blogs')->insert([
        'category_id' => 1,
        'title' => 'Hello World',
        'slug' => 'hello-world',
    ]);

    $this->assertDatabaseHas('blogs', [
        'slug' => 'hello-world',
        'status' => 1,
    ]);
});
