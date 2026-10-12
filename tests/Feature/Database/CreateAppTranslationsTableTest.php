<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('app_translations has the expected columns', function () {
    expect(Schema::hasTable('app_translations'))->toBeTrue();
    expect(Schema::hasColumns('app_translations', [
        'id', 'lang', 'lang_key', 'lang_value', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('app_translations')->insert([
        'lang' => 'en',
        'lang_key' => 'welcome',
        'lang_value' => 'Welcome',
    ]);

    $this->assertDatabaseHas('app_translations', [
        'lang' => 'en',
        'lang_key' => 'welcome',
    ]);
});
