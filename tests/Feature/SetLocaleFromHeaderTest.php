<?php

test('an api request with an Arabic Accept-Language header gets an Arabic validation message', function () {
    actingAsMobileUser();

    $response = $this->withHeaders(['Accept-Language' => 'ar'])
        ->postJson('/api/v1/pallets', []);

    $response->assertStatus(422);
    expect($response->json('errors.product_id.0'))->toContain('مطلوب');
});

test('an api request without an Arabic Accept-Language header gets the english validation message', function () {
    actingAsMobileUser();

    $response = $this->postJson('/api/v1/pallets', []);

    $response->assertStatus(422);
    expect($response->json('errors.product_id.0'))->toBe('The product id field is required.');
});
