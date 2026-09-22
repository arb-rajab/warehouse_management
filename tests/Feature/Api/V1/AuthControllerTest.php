<?php

use App\Models\User;

test('a user can log in with correct credentials and receives a token plus every user property the app reads', function () {
    $user = User::factory()->mobileUser()->create([
        'name' => 'Bob Mobile',
        'email' => 'bob@example.com',
        'password' => 'correct-password',
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'bob@example.com',
        'password' => 'correct-password',
    ]);

    $response->assertOk();

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
    expect($response->json('user'))->toEqual([
        'id' => $user->id,
        'name' => 'Bob Mobile',
        'email' => 'bob@example.com',
    ]);
    expect(array_keys($response->json()))->toEqualCanonicalizing(['token', 'user']);

    $this->assertDatabaseCount('wms_personal_access_tokens', 1);
});

test('logging in with the wrong password is rejected', function () {
    $user = User::factory()->mobileUser()->create(['password' => 'correct-password']);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseCount('wms_personal_access_tokens', 0);
});

test('logging in with an unknown email is rejected with the same message as a wrong password', function () {
    $unknownEmailResponse = $this->postJson('/api/v1/login', [
        'email' => 'nobody@example.com',
        'password' => 'whatever',
    ]);

    $user = User::factory()->mobileUser()->create(['password' => 'correct-password']);

    $wrongPasswordResponse = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $unknownEmailResponse->assertStatus(422);
    $wrongPasswordResponse->assertStatus(422);
    expect($unknownEmailResponse->json('errors.email.0'))
        ->toBe($wrongPasswordResponse->json('errors.email.0'));

    $this->assertDatabaseCount('wms_personal_access_tokens', 0);
});

test('logging in with an overly long email or password is rejected', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => str_repeat('a', 256).'@example.com',
        'password' => str_repeat('a', 256),
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    $this->assertDatabaseCount('wms_personal_access_tokens', 0);
});

test('login attempts are throttled after too many failures', function () {
    $user = User::factory()->mobileUser()->create(['password' => 'correct-password']);

    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(429);
    $this->assertDatabaseCount('wms_personal_access_tokens', 0);
});

test('login attempts against one email are throttled even when spread across many IPs', function () {
    $user = User::factory()->mobileUser()->create(['password' => 'correct-password']);

    foreach (range(1, 20) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$i"])
            ->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(422);
    }

    $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
        ->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

    $response->assertStatus(429);
    $this->assertDatabaseCount('wms_personal_access_tokens', 0);
});

test('a user can log out and their token is deleted', function () {
    $user = User::factory()->mobileUser()->create();
    $token = $user->createToken('test-device');

    $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/logout');

    $response->assertNoContent();
    $this->assertDatabaseMissing('wms_personal_access_tokens', ['id' => $token->accessToken->id]);
});

test('an unauthenticated caller cannot log out and other users tokens are untouched', function () {
    $otherUser = User::factory()->mobileUser()->create();
    $otherUser->createToken('noise-device');

    $response = $this->postJson('/api/v1/logout');

    $response->assertUnauthorized();
    $this->assertDatabaseCount('wms_personal_access_tokens', 1);
});

test('the mobile api deliberately does not enforce must_change_password', function () {
    // A settled product decision, not an oversight: EnsurePasswordHasBeenChanged
    // is registered on the `web` group only, and there is no API route to change
    // a password. Enforcing the flag on api/v1/* would need a mobile
    // change-password endpoint to escape to, and the mobile client lives outside
    // this repository — until it can call one, enforcement would lock every
    // flagged worker out of the app. This test exists so lifting the exemption
    // is a deliberate change rather than a silent one; see the docblock of
    // app/Http/Middleware/EnsurePasswordHasBeenChanged.php.
    $user = User::factory()->mobileUser()->create([
        'password' => 'temporary-password',
        'must_change_password' => true,
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'temporary-password',
    ]);

    $response->assertOk();

    $this->withToken($response->json('token'))->getJson('/api/v1/products')->assertOk();

    expect($user->refresh()->must_change_password)->toBeTrue();
});
