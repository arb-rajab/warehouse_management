<?php

use App\Models\MobileAppVersionRequirement;

test('minimumVersion returns null when no requirement has been configured', function () {
    expect(MobileAppVersionRequirement::minimumVersion())->toBeNull();
});

test('minimumVersion returns the configured minimum version', function () {
    MobileAppVersionRequirement::factory()->create(['minimum_version' => '2.1.0']);

    expect(MobileAppVersionRequirement::minimumVersion())->toBe('2.1.0');
});
