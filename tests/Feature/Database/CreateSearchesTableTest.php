<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('searches has the expected columns', function () {
    expect(Schema::hasTable('searches'))->toBeTrue();
    expect(Schema::hasColumns('searches', [
        'id', 'query', 'count', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('searches')->insert([
        'query' => 'bicycle',
    ]);

    $this->assertDatabaseHas('searches', [
        'query' => 'bicycle',
        'count' => 1,
    ]);
});
