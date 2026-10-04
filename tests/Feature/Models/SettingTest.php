<?php

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

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

test('allowed_open_levels defaults to level 1 on a freshly created row and when the column is null', function () {
    expect(Setting::current()->allowed_open_levels)->toBe([1]);

    $legacy = Setting::factory()->create();
    DB::table('wms_settings')->where('id', $legacy->id)->update(['allowed_open_levels' => null]);

    expect($legacy->fresh()->allowed_open_levels)->toBe([1]);
});

test('allowed_open_levels is stored as a sorted, de-duplicated list of ints', function () {
    $setting = Setting::factory()->create(['allowed_open_levels' => ['3', 1, 3, 2]]);

    expect($setting->fresh()->allowed_open_levels)->toBe([1, 2, 3]);
    expect(DB::table('wms_settings')->value('allowed_open_levels'))->toBe('[1,2,3]');
});

test('allowsOpeningAtLevel follows the configured levels and falls back to level 1 without creating a row', function () {
    expect(Setting::allowsOpeningAtLevel(1))->toBeTrue();
    expect(Setting::allowsOpeningAtLevel(2))->toBeFalse();
    $this->assertDatabaseCount('wms_settings', 0);

    Setting::factory()->create(['allowed_open_levels' => [2, 4]]);

    expect(Setting::allowsOpeningAtLevel(1))->toBeFalse();
    expect(Setting::allowsOpeningAtLevel(2))->toBeTrue();
    expect(Setting::allowsOpeningAtLevel(3))->toBeFalse();
    expect(Setting::allowsOpeningAtLevel(4))->toBeTrue();
});
