<?php

use App\Models\MobileAppVersionRequirement;
use Illuminate\Support\Facades\DB;

test('minimumVersion returns null when no requirement has been configured', function () {
    expect(MobileAppVersionRequirement::minimumVersion())->toBeNull();
});

test('minimumVersion returns the configured minimum version', function () {
    MobileAppVersionRequirement::factory()->create(['minimum_version' => '2.1.0']);

    expect(MobileAppVersionRequirement::minimumVersion())->toBe('2.1.0');
});

test('minimumVersion caches the database value, so a second call does not hit the database', function () {
    // This is read on every API request (EnsureMinimumAppVersion), ahead of
    // auth:sanctum, so a repeat call must be served from cache rather than
    // re-querying the single-row table each time.
    MobileAppVersionRequirement::factory()->create(['minimum_version' => '2.1.0']);

    MobileAppVersionRequirement::minimumVersion();

    DB::enableQueryLog();
    expect(MobileAppVersionRequirement::minimumVersion())->toBe('2.1.0');
    expect(DB::getQueryLog())->toBeEmpty();
});
