<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('currencies table has the expected columns', function () {
    expect(Schema::hasTable('currencies'))->toBeTrue();
    expect(Schema::hasColumns('currencies', [
        'id', 'name', 'symbol', 'exchange_rate', 'status', 'code', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('currencies')->insert([
        'name' => 'Euro',
        'symbol' => '€',
        'exchange_rate' => 0.85,
    ]);

    $this->assertDatabaseHas('currencies', [
        'name' => 'Euro',
        'status' => 0,
    ]);
});
