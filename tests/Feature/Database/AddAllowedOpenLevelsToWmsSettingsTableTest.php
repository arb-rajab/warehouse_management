<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

test('the wms_settings table has a nullable allowed_open_levels column', function () {
    expect(Schema::hasColumn('wms_settings', 'allowed_open_levels'))->toBeTrue();

    $setting = Setting::query()->create(['qr_code_width' => 300, 'qr_code_height' => 300]);

    expect($setting->fresh()->allowed_open_levels)->toBe([1]);
});
