<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('attribute_translations has the expected columns', function () {
    expect(Schema::hasTable('attribute_translations'))->toBeTrue();
    expect(Schema::hasColumns('attribute_translations', [
        'id', 'attribute_id', 'name', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('attribute_translations')->insert([
        'attribute_id' => 1,
        'name' => 'Maat',
        'lang' => 'nl',
    ]);

    $this->assertDatabaseHas('attribute_translations', [
        'attribute_id' => 1,
        'lang' => 'nl',
    ]);
});
