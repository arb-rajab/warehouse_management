<?php

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('the model reads the wms-owned token table', function () {
    expect((new PersonalAccessToken)->getTable())->toBe('wms_personal_access_tokens');
});

test('the default personal_access_tokens table is left for the store app', function () {
    expect(Schema::hasTable('personal_access_tokens'))->toBeFalse();
});

test('issuing a token writes it to the wms-owned table through this model', function () {
    $user = User::factory()->create();
    // Noise: another user's token, proving the assertions below match this
    // user's row rather than whatever happens to be in the table.
    User::factory()->create()->createToken('other device');

    $token = $user->createToken('mobile app');

    // Sanctum resolving this subclass is what proves the registration in
    // AppServiceProvider took effect — its own model would carry the default
    // table name.
    expect($token->accessToken)->toBeInstanceOf(PersonalAccessToken::class);
    $this->assertDatabaseHas('wms_personal_access_tokens', [
        'id' => $token->accessToken->id,
        'name' => 'mobile app',
        'tokenable_id' => $user->id,
        'tokenable_type' => User::class,
    ]);
    $this->assertDatabaseCount('wms_personal_access_tokens', 2);
});

test('a token issued from the wms-owned table authenticates an api request', function () {
    $user = User::factory()->mobileUser()->create();

    $this->withToken($user->createToken('mobile app')->plainTextToken)
        ->getJson('/api/v1/products')
        ->assertOk();
});

test('a token that is not in the wms-owned table is rejected', function () {
    $this->withToken('1|nonexistenttokenvalue')
        ->getJson('/api/v1/products')
        ->assertUnauthorized();
});
