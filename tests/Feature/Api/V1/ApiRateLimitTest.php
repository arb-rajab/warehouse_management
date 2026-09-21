<?php

use App\Models\Product;
use App\Models\Row;
use App\Models\User;

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

    actingAsMobileUser($userA);
    foreach (range(1, 60) as $_) {
        $this->getJson('/api/v1/products')->assertOk();
    }
    $this->getJson('/api/v1/products')->assertStatus(429);

    actingAsMobileUser($userB);
    $this->getJson('/api/v1/products')->assertOk();
});

test('two mobile clients sharing one IP get independent api rate limit buckets', function () {
    // Real bearer tokens, not Sanctum::actingAs(): actingAs() also calls
    // auth()->shouldUse('sanctum'), which makes even a guard-less
    // $request->user() resolve the token user — so a limiter keyed on the wrong
    // guard passes under actingAs() while falling back to the IP in production,
    // where throttle:api runs before auth:sanctum and nothing has switched the
    // default guard away from `web`. Both requests come from the same test IP,
    // which is the warehouse-behind-one-NAT case this keying protects.
    $tokenA = User::factory()->mobileUser()->create()->createToken('mobile')->plainTextToken;
    $tokenB = User::factory()->mobileUser()->create()->createToken('mobile')->plainTextToken;

    foreach (range(1, 60) as $_) {
        $this->withToken($tokenA)->getJson('/api/v1/products')->assertOk();
    }

    $this->withToken($tokenA)->getJson('/api/v1/products')->assertStatus(429);
    $this->withToken($tokenB)->getJson('/api/v1/products')->assertOk();
});

test('a throttled mobile client cannot store a pallet and nothing is written', function () {
    $token = User::factory()->mobileUser()->create()->createToken('mobile')->plainTextToken;

    $row = Row::factory()->create(['letter' => 'Z', 'cells_count' => 1, 'flats_count' => 1]);
    $product = Product::factory()->create();

    foreach (range(1, 60) as $_) {
        $this->withToken($token)->getJson('/api/v1/products')->assertOk();
    }

    // An otherwise valid create: without the 429 this request stores a pallet,
    // so the empty table proves the throttle rejected it before the controller.
    $response = $this->withToken($token)->postJson('/api/v1/pallets', [
        'row_letter' => $row->letter,
        'cell_number' => 1,
        'flat_number' => 1,
        'product_id' => $product->id,
        'expiration_date' => now()->addMonth()->toDateString(),
    ]);

    $response->assertStatus(429);
    $this->assertDatabaseCount('pallets', 0);
});
