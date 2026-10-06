<?php

use App\Models\StoreUser;
use App\Models\Upload;
use Illuminate\Support\Facades\Hash;

/**
 * The store back office's own-profile page (/temp/admin/profile): self-service
 * for any signed-in staff member, so no permission gate, but the update took
 * the account from the URL and let any staff member rewrite any account's
 * name, email, password and avatar.
 */
const ADMIN_PROFILE_URI = '/temp/admin/profile';

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function adminProfilePayload(array $overrides = []): array
{
    return [
        'name' => 'Renamed Staff',
        'email' => 'renamed-staff@example.com',
        'new_password' => 'a-Strong-Passw0rd!',
        'confirm_password' => 'a-Strong-Passw0rd!',
        'avatar' => (string) Upload::factory()->create()->id,
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function adminProfileSnapshot(StoreUser $user): array
{
    return $user->fresh()->only(['name', 'email', 'password', 'avatar_original']);
}

test('the profile page renders for staff', function () {
    actingAsStoreUser(createStoreStaff());

    $this->get(ADMIN_PROFILE_URI)->assertOk()->assertViewIs('shop.backend.admin_profile.index');
});

test('the profile page is refused to a guest and a customer', function (string $caller) {
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->get(ADMIN_PROFILE_URI), $caller);
})->with(['guest', 'customer']);

test('staff can update their own name, email, avatar and password', function () {
    $staff = actingAsStoreUser(createStoreStaff());
    $payload = adminProfilePayload();

    $this->patch("/temp/admin/profile/{$staff->id}", $payload)
        ->assertRedirect()
        ->assertSessionHas('flash_notification');

    $staff = $staff->fresh();
    expect($staff->name)->toBe('Renamed Staff')
        ->and($staff->email)->toBe('renamed-staff@example.com')
        ->and((string) $staff->avatar_original)->toBe($payload['avatar'])
        ->and(Hash::check('a-Strong-Passw0rd!', $staff->password))->toBeTrue();
});

test("staff cannot update another account's profile", function (string $victimType) {
    $victim = match ($victimType) {
        'staff' => createStoreStaff(),
        'super admin' => createStoreAdmin(),
        'customer' => createStoreUser('customer'),
    };
    $caller = actingAsStoreUser(createStoreStaff());
    $victimBefore = adminProfileSnapshot($victim);
    $callerBefore = adminProfileSnapshot($caller);

    $this->patch("/temp/admin/profile/{$victim->id}", adminProfilePayload())->assertForbidden();

    expect(adminProfileSnapshot($victim))->toEqual($victimBefore)
        ->and(adminProfileSnapshot($caller))->toEqual($callerBefore)
        ->and(Hash::check('correct-password', $victim->fresh()->password))->toBeTrue();
})->with(['staff', 'super admin', 'customer']);

test('a foreign id gets a 403 before validation, even for an invalid payload', function () {
    $victim = createStoreStaff();
    actingAsStoreUser(createStoreStaff());

    $this->patch("/temp/admin/profile/{$victim->id}", ['name' => ''])->assertForbidden();
});

test('a guest and a customer cannot update a profile', function (string $caller) {
    $victim = createStoreStaff();
    actingAsRefusedStoreCaller($caller);
    $victimBefore = adminProfileSnapshot($victim);

    assertStoreCallerRefused($this->patch("/temp/admin/profile/{$victim->id}", adminProfilePayload()), $caller);

    expect(adminProfileSnapshot($victim))->toEqual($victimBefore);
})->with(['guest', 'customer']);

test('an invalid profile update is rejected and changes nothing', function (array $overrides, string $errorField) {
    $other = createStoreUser('customer', 'taken@example.com');
    $staff = actingAsStoreUser(createStoreStaff());
    $before = adminProfileSnapshot($staff);
    $otherBefore = adminProfileSnapshot($other);

    $this->patch("/temp/admin/profile/{$staff->id}", adminProfilePayload($overrides))->assertSessionHasErrors($errorField);

    expect(adminProfileSnapshot($staff))->toEqual($before)
        ->and(adminProfileSnapshot($other))->toEqual($otherBefore);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'missing email' => [['email' => ''], 'email'],
    'malformed email' => [['email' => 'not-an-email'], 'email'],
    "another user's email" => [['email' => 'taken@example.com'], 'email'],
    'password mismatch' => [['confirm_password' => 'something-else-1A!'], 'new_password'],
    'weak password' => [['new_password' => 'abc', 'confirm_password' => 'abc'], 'new_password'],
    'unknown avatar' => [['avatar' => '999999'], 'avatar'],
    'avatar id list' => [['avatar' => '1,2'], 'avatar'],
]);

test('resubmitting your own email passes', function () {
    $staff = actingAsStoreUser(createStoreStaff());

    $this->patch("/temp/admin/profile/{$staff->id}", adminProfilePayload(['email' => $staff->email]))
        ->assertSessionHasNoErrors();

    expect($staff->fresh()->name)->toBe('Renamed Staff');
});

test('an empty new password leaves the password alone', function () {
    $staff = actingAsStoreUser(createStoreStaff());

    $this->patch("/temp/admin/profile/{$staff->id}", adminProfilePayload(['new_password' => '', 'confirm_password' => '']))
        ->assertSessionHasNoErrors();

    expect($staff->fresh()->name)->toBe('Renamed Staff')
        ->and(Hash::check('correct-password', $staff->fresh()->password))->toBeTrue();
});

test('the profile resource has no create, store, show, edit or destroy routes', function () {
    $staff = actingAsStoreUser(createStoreStaff());
    $before = adminProfileSnapshot($staff);

    $this->get(ADMIN_PROFILE_URI.'/create')->assertStatus(405);
    $this->get("/temp/admin/profile/{$staff->id}")->assertStatus(405);
    $this->get("/temp/admin/profile/{$staff->id}/edit")->assertNotFound();
    $this->post(ADMIN_PROFILE_URI)->assertStatus(405);
    $this->delete("/temp/admin/profile/{$staff->id}")->assertStatus(405);

    expect(StoreUser::whereKey($staff->id)->exists())->toBeTrue()
        ->and(adminProfileSnapshot($staff))->toEqual($before);
});

test('the demo mode refuses the profile update and changes nothing', function () {
    config(['store.demo_mode' => true]);
    $staff = actingAsStoreUser(createStoreStaff());
    $before = adminProfileSnapshot($staff);

    $this->patch("/temp/admin/profile/{$staff->id}", adminProfilePayload())->assertRedirect();

    expect(adminProfileSnapshot($staff))->toEqual($before);
});
