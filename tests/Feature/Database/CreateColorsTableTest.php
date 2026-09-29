<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('colors has the expected columns', function () {
    expect(Schema::hasTable('colors'))->toBeTrue();
    expect(Schema::hasColumns('colors', [
        'id', 'name', 'code', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('colors')->insert([
        'name' => 'Red',
        'code' => '#FF0000',
    ]);

    $this->assertDatabaseHas('colors', [
        'name' => 'Red',
        'code' => '#FF0000',
    ]);
});
