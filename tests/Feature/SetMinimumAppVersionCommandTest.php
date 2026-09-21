<?php

use App\Models\MobileAppVersionRequirement;

test('running the command with no prior requirement creates one', function () {
    $this->artisan('app:set-minimum-app-version', ['version' => '1.4.0'])
        ->assertExitCode(0);

    expect(MobileAppVersionRequirement::minimumVersion())->toBe('1.4.0');
    $this->assertDatabaseCount('mobile_app_version_requirements', 1);
});

test('running the command again updates the existing requirement instead of creating a second one', function () {
    MobileAppVersionRequirement::factory()->create(['minimum_version' => '1.0.0']);

    $this->artisan('app:set-minimum-app-version', ['version' => '1.5.2'])
        ->assertExitCode(0);

    expect(MobileAppVersionRequirement::minimumVersion())->toBe('1.5.2');
    $this->assertDatabaseCount('mobile_app_version_requirements', 1);
});

test('a malformed version is rejected and leaves the existing requirement untouched', function () {
    MobileAppVersionRequirement::factory()->create(['minimum_version' => '1.0.0']);

    $this->artisan('app:set-minimum-app-version', ['version' => 'not-a-version'])
        ->assertExitCode(1);

    expect(MobileAppVersionRequirement::minimumVersion())->toBe('1.0.0');
});

test('running the command invalidates a previously cached minimum version', function () {
    MobileAppVersionRequirement::factory()->create(['minimum_version' => '1.0.0']);
    expect(MobileAppVersionRequirement::minimumVersion())->toBe('1.0.0'); // warms the cache

    $this->artisan('app:set-minimum-app-version', ['version' => '2.0.0'])
        ->assertExitCode(0);

    expect(MobileAppVersionRequirement::minimumVersion())->toBe('2.0.0');
});
