<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the zones table has the expected columns', function () {
    expect(Schema::hasTable('zones'))->toBeTrue();
    expect(Schema::hasColumns('zones', [
        'id', 'name', 'status', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('zones')->insert([
        'name' => 'Europe',
        'status' => true,
    ]);

    $this->assertDatabaseHas('zones', [
        'name' => 'Europe',
        'status' => 1,
    ]);
});
