<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('attributes has the expected columns', function () {
    expect(Schema::hasTable('attributes'))->toBeTrue();
    expect(Schema::hasColumns('attributes', [
        'id', 'name', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('attributes')->insert([
        'name' => 'Size',
    ]);

    $this->assertDatabaseHas('attributes', [
        'name' => 'Size',
    ]);
});
