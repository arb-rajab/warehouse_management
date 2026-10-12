<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('business_settings has the expected columns', function () {
    expect(Schema::hasTable('business_settings'))->toBeTrue();
    expect(Schema::hasColumns('business_settings', [
        'id', 'type', 'value', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('business_settings')->insert([
        'type' => 'currency_symbol',
        'value' => '$',
        'lang' => 'en',
    ]);

    $this->assertDatabaseHas('business_settings', [
        'type' => 'currency_symbol',
        'value' => '$',
    ]);
});
