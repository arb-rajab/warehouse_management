<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('a mobile user is throttled after 60 requests to an authenticated api endpoint within a minute', function () {
    actingAsMobileUser();

    foreach (range(1, 60) as $_) {
        $this->getJson('/api/v1/products')->assertOk();
    }

    $this->getJson('/api/v1/products')->assertStatus(429);
});

test('two different mobile users each get their own 60-per-minute api rate limit budget', function () {
    $userA = User::factory()->mobileUser()->create();
    $userB = User::factory()->mobileUser()->create();

    Sanctum::actingAs($userA, ['*']);
    foreach (range(1, 60) as $_) {
        $this->getJson('/api/v1/products')->assertOk();
    }
    $this->getJson('/api/v1/products')->assertStatus(429);

    Sanctum::actingAs($userB, ['*']);
    $this->getJson('/api/v1/products')->assertOk();
});
