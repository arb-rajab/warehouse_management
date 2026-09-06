<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the mobile_app_version_requirements table has the expected columns', function () {
    expect(Schema::hasTable('mobile_app_version_requirements'))->toBeTrue();
    expect(Schema::hasColumns('mobile_app_version_requirements', [
        'id', 'minimum_version', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

test('minimum_version cannot be null', function () {
    DB::table('mobile_app_version_requirements')->insert([
        'minimum_version' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);
