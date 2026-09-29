<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('flash_deal_translations has the expected columns', function () {
    expect(Schema::hasTable('flash_deal_translations'))->toBeTrue();
    expect(Schema::hasColumns('flash_deal_translations', [
        'id', 'flash_deal_id', 'title', 'lang', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('flash_deal_translations')->insert([
        'flash_deal_id' => 1,
        'title' => 'Summer Sale',
        'lang' => 'en',
    ]);

    $this->assertDatabaseHas('flash_deal_translations', [
        'flash_deal_id' => 1,
        'title' => 'Summer Sale',
    ]);
});
