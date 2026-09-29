<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the staff table has the expected columns', function () {
    expect(Schema::hasTable('staff'))->toBeTrue();
    expect(Schema::hasColumns('staff', [
        'id', 'user_id', 'role_id', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('staff')->insert([
        'user_id' => 1,
        'role_id' => 1,
    ]);

    $this->assertDatabaseHas('staff', [
        'user_id' => 1,
        'role_id' => 1,
    ]);
});
