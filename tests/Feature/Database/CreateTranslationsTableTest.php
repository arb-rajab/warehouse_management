<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the translations table has the expected columns', function () {
    expect(Schema::hasTable('translations'))->toBeTrue();
    expect(Schema::hasColumns('translations', [
        'id', 'lang', 'lang_key', 'lang_value', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('translations')->insert([
        'lang' => 'en',
        'lang_key' => 'welcome',
        'lang_value' => 'Welcome',
    ]);

    $this->assertDatabaseHas('translations', [
        'lang' => 'en',
        'lang_key' => 'welcome',
        'lang_value' => 'Welcome',
    ]);
});
