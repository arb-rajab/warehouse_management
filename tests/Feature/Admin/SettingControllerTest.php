<?php

use App\Models\Setting;
use Inertia\Testing\AssertableInertia as Assert;

test('an admin can view the settings page with the current QR code size', function () {
    actingAsAdmin();
    Setting::factory()->create(['qr_code_width' => 300, 'qr_code_height' => 350]);

    $response = $this->get('/admin/settings');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Settings/Edit')
            ->where('setting.qr_code_width', 300)
            ->where('setting.qr_code_height', 350)
    );
});

test('the settings page renders with the default QR code size when never configured', function () {
    actingAsAdmin();

    $response = $this->get('/admin/settings');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Settings/Edit')
            ->where('setting.qr_code_width', 280)
            ->where('setting.qr_code_height', 380)
    );
});

test('a mobile app user cannot view the settings page', function () {
    actingAsMobilePanelUser();

    $response = $this->get('/admin/settings');

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when viewing the settings page', function () {
    $response = $this->get('/admin/settings');

    $response->assertRedirect(route('login'));
});

test('an admin can update the QR code size and it persists', function () {
    actingAsAdmin();
    Setting::factory()->create(['qr_code_width' => 240, 'qr_code_height' => 240]);

    $response = $this->put('/admin/settings', ['qr_code_width' => 400, 'qr_code_height' => 500]);

    $response->assertRedirect(route('admin.settings.edit'));
    $this->assertDatabaseHas('wms_settings', [
        'qr_code_width' => 400,
        'qr_code_height' => 500,
    ]);
});

test('updating the settings creates the row when it has never existed', function () {
    actingAsAdmin();
    expect(Setting::query()->count())->toBe(0);

    $this->put('/admin/settings', ['qr_code_width' => 350, 'qr_code_height' => 360]);

    $this->assertDatabaseCount('wms_settings', 1);
    $this->assertDatabaseHas('wms_settings', [
        'qr_code_width' => 350,
        'qr_code_height' => 360,
    ]);
});

test('the QR code width and height must be within the validated bounds', function () {
    actingAsAdmin();
    $setting = Setting::factory()->create(['qr_code_width' => 240, 'qr_code_height' => 240]);

    foreach ([99, 1001, -5, 'many'] as $invalid) {
        $response = $this->put('/admin/settings', ['qr_code_width' => $invalid, 'qr_code_height' => 240]);
        $response->assertSessionHasErrors('qr_code_width');
    }

    foreach ([99, 1001, -5, 'many'] as $invalid) {
        $response = $this->put('/admin/settings', ['qr_code_width' => 240, 'qr_code_height' => $invalid]);
        $response->assertSessionHasErrors('qr_code_height');
    }

    $response = $this->put('/admin/settings', []);
    $response->assertSessionHasErrors(['qr_code_width', 'qr_code_height']);

    expect($setting->fresh()->qr_code_width)->toBe(240);
    expect($setting->fresh()->qr_code_height)->toBe(240);
});

test('a mobile app user cannot update the settings', function () {
    actingAsMobilePanelUser();
    $setting = Setting::factory()->create(['qr_code_width' => 240, 'qr_code_height' => 240]);

    $response = $this->put('/admin/settings', ['qr_code_width' => 400, 'qr_code_height' => 500]);

    $response->assertForbidden();
    expect($setting->fresh()->qr_code_width)->toBe(240);
    expect($setting->fresh()->qr_code_height)->toBe(240);
});

test('an unauthenticated caller cannot update the settings', function () {
    $setting = Setting::factory()->create(['qr_code_width' => 240, 'qr_code_height' => 240]);

    $response = $this->put('/admin/settings', ['qr_code_width' => 400, 'qr_code_height' => 500]);

    $response->assertRedirect(route('login'));
    expect($setting->fresh()->qr_code_width)->toBe(240);
    expect($setting->fresh()->qr_code_height)->toBe(240);
});
