<?php

use App\Models\Row;
use Illuminate\Support\Facades\File;

/*
 * Tests\TestCase resets PHP's time limit after each test: a controller that
 * raises it for one slow web request must not put every later test in the
 * same Pest worker under that limit.
 */

test('exporting QR codes raises the time limit for its request', function () {
    $fontCache = storage_path('framework/testing/qr-fonts-'.bin2hex(random_bytes(8)));
    File::ensureDirectoryExists($fontCache);
    $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($fontCache));
    config(['dompdf.options.font_cache' => $fontCache]);
    actingAsAdmin();
    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 1]);

    $this->get("/admin/rows/{$row->letter}/export-qr-codes")->assertOk();

    expect(ini_get('max_execution_time'))->toBe('300');
});

test('the next test runs without that limit again', function () {
    expect(ini_get('max_execution_time'))->toBe('0');
})->depends('exporting QR codes raises the time limit for its request');
