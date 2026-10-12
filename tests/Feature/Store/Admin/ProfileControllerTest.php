<?php

use App\Models\StoreUser;
use App\Models\Upload;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

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
        'avatar' => (string) Upload::factory()->create(['type' => 'image'])->id,
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

    $this->get(ADMIN_PROFILE_URI)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Profile/Index'));
});

test('the profile page is refused to a guest and a customer', function (string $caller) {
    actingAsRefusedStoreCaller($caller);

    assertStoreCallerRefused($this->get(ADMIN_PROFILE_URI), $caller);
})->with(['guest', 'customer']);

test('staff can update their own name, email, avatar and password', function () {
    $staff = actingAsStoreUser(createStoreStaff());
    $payload = adminProfilePayload();

    $this->patch("/temp/admin/profile/{$staff->id}", $payload)
        ->assertRedirect(route('profile.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('flash_notification');

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

    $this->patch("/temp/admin/profile/{$staff->id}", adminProfilePayload())->assertForbidden();

    expect(adminProfileSnapshot($staff))->toEqual($before);
});

test('profile props expose only account fields and the selected preview', function () {
    $staff = actingAsStoreUser(createStoreStaff());
    $this->get(ADMIN_PROFILE_URI)->assertInertia(fn (Assert $page) => $page->has('profile', fn (Assert $profile) => $profile
        ->where('id', $staff->id)->where('name', $staff->name)->where('email', $staff->email)->where('avatar', null)));
});

test('a Super Admin cannot rewrite a foreign profile even with invalid input', function () {
    $victim = createStoreStaff();
    $before = adminProfileSnapshot($victim);
    actingAsStoreUser(createStoreAdmin());
    $this->patch('/temp/admin/profile/'.$victim->id, ['name' => ''])->assertForbidden();
    expect(adminProfileSnapshot($victim))->toEqual($before);
});

test('a missing bound profile is a 404', function () {
    actingAsStoreUser(createStoreStaff());
    $this->patch('/temp/admin/profile/999999', [])->assertNotFound();
});

test('a document avatar is refused and posted account privileges are ignored', function () {
    $staff = actingAsStoreUser(createStoreStaff());
    $document = Upload::factory()->create(['type' => 'document']);
    $before = adminProfileSnapshot($staff);
    $this->patch('/temp/admin/profile/'.$staff->id, adminProfilePayload(['avatar' => $document->id]))->assertSessionHasErrors('avatar');
    expect(adminProfileSnapshot($staff))->toEqual($before);
    $this->patch('/temp/admin/profile/'.$staff->id, adminProfilePayload(['avatar' => null, 'user_type' => 'admin', 'banned' => 1]))->assertSessionHasNoErrors();
    expect($staff->fresh()->user_type)->toBe('staff')->and($staff->fresh()->avatar_original)->toBeNull();
});

test('profile props include a full narrow selected image preview', function () {
    config(['store.asset_base_url' => 'https://store.example']);
    $staff = actingAsStoreUser(createStoreStaff());
    $avatar = Upload::factory()->stored('uploads/all/avatar.webp')->create(['file_original_name' => 'Avatar', 'type' => 'image', 'file_size' => 42]);
    $staff->forceFill(['avatar_original' => $avatar->id])->save();
    $this->get(ADMIN_PROFILE_URI)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('profile', fn (Assert $profile) => $profile
            ->where('id', $staff->id)->where('name', $staff->name)->where('email', $staff->email)
            ->has('avatar', fn (Assert $image) => $image->where('id', $avatar->id)->where('name', 'Avatar')
                ->where('type', 'image')->where('size', 42)->where('url', 'https://store.example/uploads/all/avatar.webp'))));
});

test('a banned staff account cannot read or write its profile', function () {
    $staff = createStoreStaff();
    $staff->forceFill(['banned' => 1])->save();
    actingAsStoreUser($staff);
    $before = adminProfileSnapshot($staff);
    $this->get(ADMIN_PROFILE_URI)->assertNotFound();
    $this->patch('/temp/admin/profile/'.$staff->id, adminProfilePayload())->assertNotFound();
    expect(adminProfileSnapshot($staff))->toEqual($before);
});
