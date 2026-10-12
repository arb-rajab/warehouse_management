<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('addons has the expected columns', function () {
    expect(Schema::hasTable('addons'))->toBeTrue();
    expect(Schema::hasColumns('addons', [
        'id', 'name', 'unique_identifier', 'version', 'activated', 'image',
        'purchase_code', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('addons')->insert([
        'name' => 'Sample Addon',
        'unique_identifier' => 'sample-addon',
        'version' => '1.0.0',
        'purchase_code' => 'abc123',
    ]);

    $this->assertDatabaseHas('addons', [
        'name' => 'Sample Addon',
        'activated' => 1,
    ]);
});
