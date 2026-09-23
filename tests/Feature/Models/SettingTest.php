<?php

use App\Models\Setting;

test('current creates the singleton row with default QR dimensions the first time it is read', function () {
    expect(Setting::query()->count())->toBe(0);

    $setting = Setting::current();

    expect($setting->exists)->toBeTrue();
    expect($setting->qr_code_width)->toBe(850);
    expect($setting->qr_code_height)->toBe(1000);
    $this->assertDatabaseCount('wms_settings', 1);
});

test('current returns the existing row instead of creating a second one', function () {
    Setting::factory()->create(['qr_code_width' => 500, 'qr_code_height' => 600]);

    $setting = Setting::current();

    expect($setting->qr_code_width)->toBe(500);
    expect($setting->qr_code_height)->toBe(600);
    $this->assertDatabaseCount('wms_settings', 1);
});

test('qr_code_width and qr_code_height are clamped to the validated bounds when read', function () {
    $setting = Setting::factory()->create(['qr_code_width' => 5, 'qr_code_height' => 5000]);

    expect($setting->fresh()->qr_code_width)->toBe(Setting::MIN_QR_CODE_SIZE);
    expect($setting->fresh()->qr_code_height)->toBe(Setting::MAX_QR_CODE_SIZE);
});
