<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('languages has the expected columns', function () {
    expect(Schema::hasTable('languages'))->toBeTrue();
    expect(Schema::hasColumns('languages', [
        'id', 'name', 'code', 'app_lang_code', 'rtl', 'status', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('languages')->insert([
        'name' => 'English',
        'code' => 'en',
    ]);

    $this->assertDatabaseHas('languages', [
        'name' => 'English',
        'app_lang_code' => 'en',
        'rtl' => 0,
        'status' => 1,
    ]);
});
