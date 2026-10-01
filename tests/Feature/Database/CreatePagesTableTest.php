<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('pages has the expected columns', function () {
    expect(Schema::hasTable('pages'))->toBeTrue();
    expect(Schema::hasColumns('pages', [
        'id', 'type', 'title', 'slug', 'content', 'meta_title', 'meta_description',
        'keywords', 'meta_image', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('pages')->insert([
        'type' => 'about-us',
    ]);

    $this->assertDatabaseHas('pages', [
        'type' => 'about-us',
    ]);
});
