<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('countries has the expected columns', function () {
    expect(Schema::hasTable('countries'))->toBeTrue();
    expect(Schema::hasColumns('countries', [
        'id', 'code', 'name', 'zone_id', 'status', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();

    DB::table('countries')->insert([
        'code' => 'NL',
        'name' => 'Netherlands',
    ]);

    $this->assertDatabaseHas('countries', [
        'code' => 'NL',
        'name' => 'Netherlands',
        'zone_id' => 0,
        'status' => 1,
    ]);
});
