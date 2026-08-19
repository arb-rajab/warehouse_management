<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

test('pulse.allowed_ips falls back to telescope.allowed_ips when PULSE_ALLOWED_IPS is unset', function () {
    expect(getenv('PULSE_ALLOWED_IPS'))->toBeFalse('this test only proves the fallback when PULSE_ALLOWED_IPS is not set in the environment');

    expect(config('pulse.allowed_ips'))->toBe(config('telescope.allowed_ips'));
});

test('cache can unserialize the Collection/stdClass/CarbonImmutable payloads Pulse dashboard cards store', function () {
    // Pulse's dashboard cards use the "database" store in production (config('cache.default')),
    // which actually serializes values, unlike the "array" store this test suite runs on by default.
    $store = Cache::store('database');
    $key = 'pulse-cache-serializable-classes-test';

    $payload = collect([
        (object) ['class' => 'Exception', 'location' => 'app/Foo.php:1', 'latest' => CarbonImmutable::now(), 'count' => 3],
    ]);

    $store->put($key, $payload, now()->addMinute());
    $restored = $store->get($key);

    expect($restored)->toBeInstanceOf(Collection::class)
        ->and($restored->isEmpty())->toBeFalse()
        ->and($restored->first())->toBeInstanceOf(stdClass::class)
        ->and($restored->first()->latest)->toBeInstanceOf(CarbonImmutable::class);
});
