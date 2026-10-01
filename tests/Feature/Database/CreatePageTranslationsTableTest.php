<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('page_translations has the expected columns', function () {
    expect(Schema::hasTable('page_translations'))->toBeTrue();
    expect(Schema::hasColumns('page_translations', [
        'id', 'page_id', 'title', 'content', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('page_translations')->insert([
        'page_id' => 1,
        'title' => 'About Us',
        'content' => 'Some content',
        'lang' => 'en',
    ]);

    $this->assertDatabaseHas('page_translations', [
        'page_id' => 1,
        'title' => 'About Us',
        'lang' => 'en',
    ]);
});
