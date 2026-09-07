<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('an admin passes the viewHealth gate', function () {
    $admin = actingAsAdmin();

    expect(Gate::allows('viewHealth', $admin))->toBeTrue();
});

test('a non-admin user fails the viewHealth gate', function () {
    $user = User::factory()->mobileUser()->create();

    expect(Gate::allows('viewHealth', $user))->toBeFalse();
});

test('a guest fails the viewHealth gate', function () {
    expect(Gate::allows('viewHealth'))->toBeFalse();
});

test('the health route rejects a non-admin user', function () {
    $user = User::factory()->mobileUser()->create();

    $this->actingAs($user)->get('/health')->assertForbidden();
});

test('the health route rejects a guest', function () {
    $this->get('/health')->assertForbidden();
});

test('the health route rejects an admin from an IP outside the allowlist', function () {
    // Unlike Telescope/Pulse, the RestrictToAllowedIps middleware isn't wired
    // through a config('*.middleware') array here — it's appended directly on
    // the Route::get('health', ...) definition in routes/web.php, so nothing
    // else proves it's actually attached to this route. An admin still gets
    // blocked because the middleware runs after the `can:viewHealth` gate and
    // aborts with a 403 before HealthCheckResultsController (and its query
    // against the unmigrated 'health' connection) ever runs.
    actingAsAdmin();
    config(['health.allowed_ips' => '10.0.0.5']);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
        ->get('/health');

    $response->assertForbidden();
});

test('health.allowed_ips falls back to telescope.allowed_ips when HEALTH_ALLOWED_IPS is unset', function () {
    expect(getenv('HEALTH_ALLOWED_IPS'))->toBeFalse('this test only proves the fallback when HEALTH_ALLOWED_IPS is not set in the environment');

    expect(config('health.allowed_ips'))->toBe(config('telescope.allowed_ips'));
});
