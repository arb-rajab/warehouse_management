<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('flash_deals has the expected columns', function () {
    expect(Schema::hasTable('flash_deals'))->toBeTrue();
    expect(Schema::hasColumns('flash_deals', [
        'id', 'title', 'start_date', 'end_date', 'status', 'featured', 'background_color',
        'text_color', 'banner', 'slug', 'created_at', 'updated_at',
    ]))->toBeTrue();

    DB::table('flash_deals')->insert([
        'title' => 'Summer Sale',
    ]);

    $this->assertDatabaseHas('flash_deals', [
        'title' => 'Summer Sale',
        'status' => 0,
        'featured' => 0,
    ]);
});
