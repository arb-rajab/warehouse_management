<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('city_translations has the expected columns', function () {
    expect(Schema::hasTable('city_translations'))->toBeTrue();
    expect(Schema::hasColumns('city_translations', [
        'id', 'city_id', 'name', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('city_translations')->insert([
        'city_id' => 1,
        'name' => 'Amsterdam',
        'lang' => 'en',
    ]);

    $this->assertDatabaseHas('city_translations', [
        'city_id' => 1,
        'name' => 'Amsterdam',
        'lang' => 'en',
    ]);
});
