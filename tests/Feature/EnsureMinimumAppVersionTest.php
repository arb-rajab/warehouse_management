<?php

use App\Models\MobileAppVersionRequirement;
use App\Models\User;

test('a request is allowed through when no minimum app version is configured', function () {
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertOk();
});

test('a request from a client below the minimum version is rejected with 426', function () {
    MobileAppVersionRequirement::factory()->create(['minimum_version' => '2.0.0']);
    actingAsMobileUser();

    $response = $this->withHeaders(['X-App-Version' => '1.9.9'])->getJson('/api/v1/dashboard');

    $response->assertStatus(426);
    expect($response->json('error_code'))->toBe('app_version_outdated');
    expect($response->json('minimum_version'))->toBe('2.0.0');
});

test('a request with no X-App-Version header is rejected once a minimum is configured', function () {
    MobileAppVersionRequirement::factory()->create(['minimum_version' => '2.0.0']);
    actingAsMobileUser();

    $response = $this->getJson('/api/v1/dashboard');

    $response->assertStatus(426);
});

test('a request at or above the minimum version is allowed through', function () {
    MobileAppVersionRequirement::factory()->create(['minimum_version' => '2.0.0']);
    actingAsMobileUser();

    $response = $this->withHeaders(['X-App-Version' => '2.0.0'])->getJson('/api/v1/dashboard');

    $response->assertOk();
});

test('a login attempt from an outdated client is rejected before authentication is attempted', function () {
    MobileAppVersionRequirement::factory()->create(['minimum_version' => '2.0.0']);
    $user = User::factory()->mobileUser()->create(['password' => 'correct-password']);

    $response = $this->withHeaders(['X-App-Version' => '1.0.0'])->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertStatus(426);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});
