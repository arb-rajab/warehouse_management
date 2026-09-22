<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the settings table exists with qr_code_width/qr_code_height defaulting to 240', function () {
    expect(Schema::hasTable('wms_settings'))->toBeTrue();

    DB::table('wms_settings')->insert(['created_at' => now(), 'updated_at' => now()]);

    $this->assertDatabaseHas('wms_settings', [
        'qr_code_width' => 240,
        'qr_code_height' => 240,
    ]);
});
