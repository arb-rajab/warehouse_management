<?php

use App\Models\Row;
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
            ->where('setting.qr_code_width', 850)
            ->where('setting.qr_code_height', 1000)
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

    $response = $this->put('/admin/settings', ['qr_code_width' => 400, 'qr_code_height' => 500, 'allowed_open_levels' => [1]]);

    $response->assertRedirect(route('admin.settings.edit'));
    $this->assertDatabaseHas('wms_settings', [
        'qr_code_width' => 400,
        'qr_code_height' => 500,
    ]);
});

test('updating the settings creates the row when it has never existed', function () {
    actingAsAdmin();
    expect(Setting::query()->count())->toBe(0);

    $this->put('/admin/settings', ['qr_code_width' => 350, 'qr_code_height' => 360, 'allowed_open_levels' => [1]]);

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
        $response = $this->put('/admin/settings', ['qr_code_width' => $invalid, 'qr_code_height' => 240, 'allowed_open_levels' => [1]]);
        $response->assertSessionHasErrors('qr_code_width');
    }

    foreach ([99, 1001, -5, 'many'] as $invalid) {
        $response = $this->put('/admin/settings', ['qr_code_width' => 240, 'qr_code_height' => $invalid, 'allowed_open_levels' => [1]]);
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

    $response = $this->put('/admin/settings', ['qr_code_width' => 400, 'qr_code_height' => 500, 'allowed_open_levels' => [1]]);

    $response->assertForbidden();
    expect($setting->fresh()->qr_code_width)->toBe(240);
    expect($setting->fresh()->qr_code_height)->toBe(240);
});

test('an unauthenticated caller cannot update the settings', function () {
    $setting = Setting::factory()->create(['qr_code_width' => 240, 'qr_code_height' => 240]);

    $response = $this->put('/admin/settings', ['qr_code_width' => 400, 'qr_code_height' => 500, 'allowed_open_levels' => [1]]);

    $response->assertRedirect(route('login'));
    expect($setting->fresh()->qr_code_width)->toBe(240);
    expect($setting->fresh()->qr_code_height)->toBe(240);
});

test('the settings page offers every level up to the tallest row and defaults to level 1 checked', function () {
    actingAsAdmin();
    Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 2]);
    Row::factory()->create(['letter' => 'B', 'cells_count' => 1, 'flats_count' => 4]);

    $this->get('/admin/settings')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Settings/Edit')
            ->where('highestLevel', 4)
            ->where('setting.allowed_open_levels', [1])
    );
});

test('the settings page shows the saved allowed open levels', function () {
    actingAsAdmin();
    Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 3]);
    Setting::factory()->create(['allowed_open_levels' => [2, 3]]);

    $this->get('/admin/settings')->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('setting.allowed_open_levels', [2, 3])
    );
});

test('an admin can update the allowed open levels and they persist sorted', function () {
    actingAsAdmin();
    Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 3]);
    Setting::factory()->create();

    $response = $this->put('/admin/settings', [
        'qr_code_width' => 400,
        'qr_code_height' => 500,
        'allowed_open_levels' => ['3', '1', '3'],
    ]);

    $response->assertRedirect(route('admin.settings.edit'));
    expect(Setting::current()->allowed_open_levels)->toBe([1, 3]);
});

test('the allowed open levels must be a non-empty list of existing levels', function () {
    actingAsAdmin();
    Row::factory()->create(['letter' => 'A', 'cells_count' => 1, 'flats_count' => 3]);
    $setting = Setting::factory()->create(['allowed_open_levels' => [2]]);
    $base = ['qr_code_width' => 400, 'qr_code_height' => 500];

    $this->put('/admin/settings', $base)->assertSessionHasErrors('allowed_open_levels');
    $this->put('/admin/settings', [...$base, 'allowed_open_levels' => []])->assertSessionHasErrors('allowed_open_levels');
    $this->put('/admin/settings', [...$base, 'allowed_open_levels' => 'all'])->assertSessionHasErrors('allowed_open_levels');

    foreach ([0, 4, -1, 'top', 1.5] as $invalid) {
        $this->put('/admin/settings', [...$base, 'allowed_open_levels' => [$invalid]])
            ->assertSessionHasErrors('allowed_open_levels.0');
    }

    expect($setting->fresh()->allowed_open_levels)->toBe([2]);
});

test('a mobile app user cannot update the allowed open levels', function () {
    actingAsMobilePanelUser();
    $setting = Setting::factory()->create(['allowed_open_levels' => [1]]);

    $this->put('/admin/settings', ['qr_code_width' => 400, 'qr_code_height' => 500, 'allowed_open_levels' => [1, 2]])
        ->assertForbidden();

    expect($setting->fresh()->allowed_open_levels)->toBe([1]);
});

test('an unauthenticated caller cannot update the allowed open levels', function () {
    $setting = Setting::factory()->create(['allowed_open_levels' => [1]]);

    $this->put('/admin/settings', ['qr_code_width' => 400, 'qr_code_height' => 500, 'allowed_open_levels' => [1, 2]])
        ->assertRedirect(route('login'));

    expect($setting->fresh()->allowed_open_levels)->toBe([1]);
});
