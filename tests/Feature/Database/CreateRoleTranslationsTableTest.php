<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('role_translations has the expected columns', function () {
    expect(Schema::hasTable('role_translations'))->toBeTrue();
    expect(Schema::hasColumns('role_translations', [
        'id', 'role_id', 'name', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('role_translations')->insert([
        'role_id' => 1,
        'name' => 'Super Admin',
        'lang' => 'en',
    ]);

    $this->assertDatabaseHas('role_translations', [
        'role_id' => 1,
        'name' => 'Super Admin',
    ]);
});
