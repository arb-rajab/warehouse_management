<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('an admin passes the viewPulse gate', function () {
    $admin = actingAsAdmin();

    expect(Gate::allows('viewPulse', $admin))->toBeTrue();
});

test('a non-admin user fails the viewPulse gate', function () {
    $user = User::factory()->mobileUser()->create();

    expect(Gate::allows('viewPulse', $user))->toBeFalse();
});

test('a guest fails the viewPulse gate', function () {
    expect(Gate::allows('viewPulse'))->toBeFalse();
});

test('the pulse route middleware is IP-restricted via pulse.allowed_ips', function () {
    expect(config('pulse.middleware'))->toContain('App\Http\Middleware\RestrictToAllowedIps:pulse.allowed_ips');
});

test('the pulse dashboard route rejects a guest', function () {
    $this->get('/pulse')->assertForbidden();
});

test('the pulse dashboard route rejects a non-admin user', function () {
    $user = User::factory()->mobileUser()->create();

    $this->actingAs($user)->get('/pulse')->assertForbidden();
});

test('pulse.allowed_ips falls back to telescope.allowed_ips when PULSE_ALLOWED_IPS is unset', function () {
    expect(getenv('PULSE_ALLOWED_IPS'))->toBeFalse('this test only proves the fallback when PULSE_ALLOWED_IPS is not set in the environment');

    expect(config('pulse.allowed_ips'))->toBe(config('telescope.allowed_ips'));
});
