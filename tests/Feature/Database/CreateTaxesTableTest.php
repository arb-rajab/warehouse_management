<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the taxes table has the expected columns', function () {
    expect(Schema::hasTable('taxes'))->toBeTrue();
    expect(Schema::hasColumns('taxes', [
        'id', 'name', 'tax_status', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('taxes')->insert([
        'name' => 'VAT',
    ]);

    $this->assertDatabaseHas('taxes', [
        'name' => 'VAT',
        'tax_status' => 1,
    ]);
});
