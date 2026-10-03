<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create([
        'password' => 'old-password',
        'must_change_password' => true,
    ]);
});

test('the change password screen can be rendered', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $response = $this->actingAs($user)->get('/password/change');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Auth/ChangePassword')
    );
});

test('an unauthenticated caller is redirected to login when viewing the change password page', function () {
    $response = $this->get('/password/change');

    $response->assertRedirect(route('login'));
});

test('an unauthenticated caller cannot submit the change password form', function () {
    $response = $this->put('/password/change', [
        'current_password' => 'old-password',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a user can change their password, which clears the must_change_password flag', function () {
    $response = $this->actingAs($this->user)->put('/password/change', [
        'current_password' => 'old-password',
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ]);

    $response->assertRedirect(route('admin.dashboard'));

    $fresh = $this->user->fresh();
    expect($fresh->must_change_password)->toBeFalse();
    expect(Hash::check('new-strong-password', $fresh->password))->toBeTrue();
    expect(Hash::check('old-password', $fresh->password))->toBeFalse();
});

test('changing the password redirects away once the flag is cleared', function () {
    $this->actingAs($this->user)->put('/password/change', [
        'current_password' => 'old-password',
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ]);

    $response = $this->actingAs($this->user->fresh())->get('/admin');

    $response->assertOk();
});

test('submitting invalid data redirects back to the change-password form, not the dashboard', function () {
    // The last *hard* page load for a must-change-password user is /admin
    // (EnsurePasswordHasBeenChanged then redirects them onward) — this is
    // what leaves session `_previous.url` stale at /admin under the pre-fix
    // behavior.
    $this->actingAs($this->user)->get('/admin');

    // The Vue page's own visit still carries X-Requested-With, which is
    // exactly what used to make Laravel skip updating `_previous.url` for it.
    $this->withHeaders(inertiaHeaders())->get('/password/change');

    $response = $this->withHeaders(inertiaHeaders())->put('/password/change', [
        'current_password' => 'old-password',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertRedirect(route('password.change'));
    $response->assertSessionHasErrors('password');
});

test('submitting a mismatched password confirmation is rejected and nothing changes', function () {
    $response = $this->actingAs($this->user)->put('/password/change', [
        'current_password' => 'old-password',
        'password' => 'new-strong-password',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertSessionHasErrors('password');

    $fresh = $this->user->fresh();
    expect($fresh->must_change_password)->toBeTrue();
    expect(Hash::check('old-password', $fresh->password))->toBeTrue();
});

test('submitting an overly short password is rejected and nothing changes', function () {
    $response = $this->actingAs($this->user)->put('/password/change', [
        'current_password' => 'old-password',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertSessionHasErrors('password');

    $fresh = $this->user->fresh();
    expect($fresh->must_change_password)->toBeTrue();
    expect(Hash::check('old-password', $fresh->password))->toBeTrue();
});

test('submitting the wrong current password is rejected and nothing changes', function () {
    $response = $this->actingAs($this->user)->put('/password/change', [
        'current_password' => 'not-the-right-password',
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ]);

    $response->assertSessionHasErrors('current_password');

    $fresh = $this->user->fresh();
    expect($fresh->must_change_password)->toBeTrue();
    expect(Hash::check('old-password', $fresh->password))->toBeTrue();
});

test('submitting the current password as the new password is rejected and nothing changes', function () {
    $response = $this->actingAs($this->user)->put('/password/change', [
        'current_password' => 'old-password',
        'password' => 'old-password',
        'password_confirmation' => 'old-password',
    ]);

    $response->assertSessionHasErrors(['password' => __('messages.password_same_as_current')]);

    $fresh = $this->user->fresh();
    expect($fresh->must_change_password)->toBeTrue();
    expect(Hash::check('old-password', $fresh->password))->toBeTrue();
});

test('submitting without a current password is rejected and nothing changes', function () {
    $response = $this->actingAs($this->user)->put('/password/change', [
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ]);

    $response->assertSessionHasErrors('current_password');

    $fresh = $this->user->fresh();
    expect($fresh->must_change_password)->toBeTrue();
    expect(Hash::check('old-password', $fresh->password))->toBeTrue();
});

/**
 * Changing the password left every other login alive: a stolen token or
 * session survived the change meant to lock it out.
 */
function changePassword(User $user, string $currentPassword = 'old-password')
{
    return test()->actingAs($user)->put('/password/change', [
        'current_password' => $currentPassword,
        'password' => 'new-strong-password',
        'password_confirmation' => 'new-strong-password',
    ]);
}

function insertSession(string $id, int $userId): void
{
    DB::table('wms_sessions')->insert([
        'id' => $id, 'user_id' => $userId, 'ip_address' => '127.0.0.1', 'user_agent' => 'test',
        'payload' => '', 'last_activity' => now()->timestamp,
    ]);
}

test('changing the password revokes every api token of the user and no one else\'s', function () {
    $this->user->createToken('phone');
    $this->user->createToken('tablet');
    $bystander = User::factory()->create();
    $bystander->createToken('noise');

    changePassword($this->user)->assertRedirect(route('admin.dashboard'));

    expect($this->user->tokens()->count())->toBe(0)
        ->and($bystander->tokens()->count())->toBe(1);
});

test('changing the password ends the user\'s other sessions and no one else\'s', function () {
    $bystander = User::factory()->create();
    insertSession('stale-session-1', $this->user->id);
    insertSession('stale-session-2', $this->user->id);
    insertSession('bystander-session', $bystander->id);

    changePassword($this->user)->assertRedirect(route('admin.dashboard'));

    $this->assertDatabaseMissing('wms_sessions', ['id' => 'stale-session-1']);
    $this->assertDatabaseMissing('wms_sessions', ['id' => 'stale-session-2']);
    $this->assertDatabaseHas('wms_sessions', ['id' => 'bystander-session']);
});

test('a rejected password change revokes nothing', function () {
    $this->user->createToken('phone');
    insertSession('stale-session-1', $this->user->id);

    changePassword($this->user, 'not-the-right-password')->assertSessionHasErrors('current_password');

    expect($this->user->tokens()->count())->toBe(1);
    $this->assertDatabaseHas('wms_sessions', ['id' => 'stale-session-1']);
});

test('the change-password form takes five tries a minute, then refuses even the right password', function () {
    foreach (range(1, 5) as $attempt) {
        changePassword($this->user, 'not-the-right-password')->assertSessionHasErrors('current_password');
    }

    changePassword($this->user)->assertTooManyRequests();

    expect(Hash::check('old-password', $this->user->fresh()->password))->toBeTrue();
});

test('one user hitting the change-password limit does not limit another', function () {
    foreach (range(1, 6) as $attempt) {
        changePassword($this->user, 'not-the-right-password');
    }
    $other = User::factory()->create(['password' => 'old-password']);

    changePassword($other)->assertRedirect(route('admin.dashboard'));
});
