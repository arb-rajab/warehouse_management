<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('brand_translations has the expected columns', function () {
    expect(Schema::hasTable('brand_translations'))->toBeTrue();
    expect(Schema::hasColumns('brand_translations', [
        'id', 'brand_id', 'name', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('brand_translations')->insert([
        'brand_id' => 1,
        'name' => 'Acme NL',
        'lang' => 'nl',
    ]);

    $this->assertDatabaseHas('brand_translations', [
        'brand_id' => 1,
        'lang' => 'nl',
    ]);
});
