<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the states table has the expected columns', function () {
    expect(Schema::hasTable('states'))->toBeTrue();
    expect(Schema::hasColumns('states', [
        'id', 'name', 'country_id', 'status', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();

    DB::table('states')->insert([
        'name' => 'North Holland',
        'country_id' => 1,
    ]);

    $this->assertDatabaseHas('states', [
        'name' => 'North Holland',
        'country_id' => 1,
        'status' => 0,
    ]);
});
