<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('cities has the expected columns', function () {
    expect(Schema::hasTable('cities'))->toBeTrue();
    expect(Schema::hasColumns('cities', [
        'id', 'name', 'state_id', 'cost', 'status', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();

    DB::table('cities')->insert([
        'name' => 'Amsterdam',
        'state_id' => 1,
    ]);

    $this->assertDatabaseHas('cities', [
        'name' => 'Amsterdam',
        'cost' => 0.00,
        'status' => 1,
    ]);
});
