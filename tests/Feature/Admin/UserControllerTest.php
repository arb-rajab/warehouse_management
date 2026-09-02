<?php

use App\Models\CellStatusLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated admin can view the user list with every property the table renders', function () {
    $admin = User::factory()->create(['name' => 'Alice Admin', 'email' => 'alice@example.com']);
    $mobileUser = User::factory()->mobileUser()->create(['name' => 'Bob Mobile', 'email' => 'bob@example.com']);

    $response = $this->actingAs($admin)->get('/admin/users');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Users/Index')
            ->has('users.data', 2)
            ->has('users.data.0', fn (Assert $user) => $user
                ->where('id', $admin->id)
                ->where('name', 'Alice Admin')
                ->where('email', 'alice@example.com')
                ->where('is_admin', true)
            )
            ->has('users.data.1', fn (Assert $user) => $user
                ->where('id', $mobileUser->id)
                ->where('name', 'Bob Mobile')
                ->where('email', 'bob@example.com')
                ->where('is_admin', false)
            )
    );
});

test('the user list paginates instead of returning everything at once', function () {
    actingAsAdmin();
    User::factory()->count(25)->mobileUser()->create();

    $response = $this->get('/admin/users');

    assertInertiaPaginates($response, 'users', 20, 26, 'Admin/Users/Index');
});

test('the user list respects a per_page query parameter', function () {
    actingAsAdmin();
    User::factory()->count(25)->mobileUser()->create();

    $response = $this->get('/admin/users?per_page=10');

    assertInertiaPaginates($response, 'users', 10, 26, 'Admin/Users/Index');
    $response->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 10));
});

test('an out-of-range per_page value falls back to the default page size for the user list', function () {
    actingAsAdmin();
    User::factory()->count(25)->mobileUser()->create();

    $response = $this->get('/admin/users?per_page=999');

    assertInertiaPaginates($response, 'users', 20, 26, 'Admin/Users/Index');
});

test('a mobile app user cannot view the user list', function () {
    actingAsMobilePanelUser();

    $response = $this->get('/admin/users');

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when viewing the user list', function () {
    $response = $this->get('/admin/users');

    $response->assertRedirect(route('login'));
});

test('an authenticated admin can view the create user page', function () {
    actingAsAdmin();

    $response = $this->get('/admin/users/create');

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Users/Create')
    );
});

test('a mobile app user cannot view the create user page', function () {
    actingAsMobilePanelUser();

    $response = $this->get('/admin/users/create');

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when viewing the create user page', function () {
    $response = $this->get('/admin/users/create');

    $response->assertRedirect(route('login'));
});

test('an authenticated admin can view a users action history with every property the table renders', function () {
    actingAsAdmin();
    $target = User::factory()->mobileUser()->create(['name' => 'Bob Mover']);
    $log = CellStatusLog::factory()->create(['user_id' => $target->id, 'note' => 'Handled with care']);

    $response = $this->get("/admin/users/{$target->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Users/Show')
            ->has('user', fn (Assert $user) => $user
                ->where('id', $target->id)
                ->where('name', 'Bob Mover')
                ->etc()
            )
            ->has('logs.data', 1)
            ->has('logs.data.0', fn (Assert $logProp) => $logProp
                ->where('id', $log->id)
                ->where('note', 'Handled with care')
                ->etc()
            )
    );
});

test('a users action history only shows their own logs, excluding another users', function () {
    actingAsAdmin();
    $target = User::factory()->mobileUser()->create();
    $otherUser = User::factory()->mobileUser()->create();

    $matching = CellStatusLog::factory()->create(['user_id' => $target->id]);
    CellStatusLog::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->get("/admin/users/{$target->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 1)
            ->where('logs.data.0.id', $matching->id)
    );
});

test('a users action history paginates instead of returning everything at once', function () {
    actingAsAdmin();
    $target = User::factory()->mobileUser()->create();
    CellStatusLog::factory()->count(30)->create(['user_id' => $target->id]);

    $response = $this->get("/admin/users/{$target->id}");

    assertInertiaPaginates($response, 'logs', 20, 30);
});

test('a users action history respects a per_page query parameter', function () {
    actingAsAdmin();
    $target = User::factory()->mobileUser()->create();
    CellStatusLog::factory()->count(30)->create(['user_id' => $target->id]);

    $response = $this->get("/admin/users/{$target->id}?per_page=10");

    assertInertiaPaginates($response, 'logs', 10, 30);
    $response->assertInertia(fn (Assert $page) => $page->where('filters.per_page', 10));
});

test('an out-of-range per_page value falls back to the default page size for a users action history', function () {
    actingAsAdmin();
    $target = User::factory()->mobileUser()->create();
    CellStatusLog::factory()->count(30)->create(['user_id' => $target->id]);

    $response = $this->get("/admin/users/{$target->id}?per_page=999");

    assertInertiaPaginates($response, 'logs', 20, 30);
});

test('a users action history defaults to newest-first', function () {
    actingAsAdmin();
    $target = User::factory()->mobileUser()->create();

    $older = backdate(CellStatusLog::factory()->create(['user_id' => $target->id]), '2026-08-01 10:00:00');
    $newer = backdate(CellStatusLog::factory()->create(['user_id' => $target->id]), '2026-08-01 12:00:00');

    $response = $this->get("/admin/users/{$target->id}");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('logs.data', 2)
            ->where('logs.data.0.id', $newer->id)
            ->where('logs.data.1.id', $older->id)
    );
});

test('a mobile app user cannot view a users action history', function () {
    actingAsMobilePanelUser();
    $target = User::factory()->mobileUser()->create();

    $response = $this->get("/admin/users/{$target->id}");

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when viewing a users action history', function () {
    $target = User::factory()->mobileUser()->create();

    $response = $this->get("/admin/users/{$target->id}");

    $response->assertRedirect(route('login'));
});

test('viewing the action history for a non-existent user returns a 404', function () {
    actingAsAdmin();

    $response = $this->get('/admin/users/999999');

    $response->assertNotFound();
});

test('an admin can create a mobile user', function () {
    actingAsAdmin();

    $response = $this->post('/admin/users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_admin' => '0',
    ]);

    $response->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'jane@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->isAdmin())->toBeFalse();
});

test('an admin can create another admin user', function () {
    actingAsAdmin();

    $response = $this->post('/admin/users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_admin' => '1',
    ]);

    $response->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'jane@example.com')->first();
    expect($user->isAdmin())->toBeTrue();
});

test('creating a user with a duplicate email is rejected and nothing changes', function () {
    actingAsAdmin();
    User::factory()->create(['email' => 'jane@example.com']);

    $response = $this->post('/admin/users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_admin' => '0',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertDatabaseCount('wms_users', 2);
});

test('creating a user with mismatched password confirmation is rejected and nothing changes', function () {
    actingAsAdmin();

    $response = $this->post('/admin/users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'not-the-same',
        'is_admin' => '0',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertDatabaseCount('wms_users', 1);
});

test('a mobile app user cannot create a user and nothing changes', function () {
    actingAsMobilePanelUser();

    $response = $this->post('/admin/users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_admin' => '0',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('wms_users', 1);
});

test('an unauthenticated caller cannot create a user and nothing changes', function () {
    $response = $this->post('/admin/users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_admin' => '0',
    ]);

    $response->assertRedirect(route('login'));
    $this->assertDatabaseCount('wms_users', 0);
});

test('creating a user with an overly long password is rejected and nothing changes', function () {
    actingAsAdmin();
    $overlyLongPassword = str_repeat('a', 256);

    $response = $this->post('/admin/users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => $overlyLongPassword,
        'password_confirmation' => $overlyLongPassword,
        'is_admin' => '0',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertDatabaseCount('wms_users', 1);
});

test('an authenticated admin can view the edit user page with every property the form needs', function () {
    actingAsAdmin();
    $target = User::factory()->mobileUser()->create(['name' => 'Bob Mobile', 'email' => 'bob@example.com']);

    $response = $this->get("/admin/users/{$target->id}/edit");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Users/Edit')
            ->has('user', fn (Assert $user) => $user
                ->where('id', $target->id)
                ->where('name', 'Bob Mobile')
                ->where('email', 'bob@example.com')
                ->where('is_admin', false)
            )
    );
});

test('the edit user page exposes an admin target as an admin', function () {
    actingAsAdmin();
    $target = User::factory()->create();

    $response = $this->get("/admin/users/{$target->id}/edit");

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Admin/Users/Edit')
            ->where('user.is_admin', true)
    );
});

test('a mobile app user cannot view the edit user page', function () {
    actingAsMobilePanelUser();
    $target = User::factory()->mobileUser()->create();

    $response = $this->get("/admin/users/{$target->id}/edit");

    $response->assertForbidden();
});

test('an unauthenticated caller is redirected to login when viewing the edit user page', function () {
    $target = User::factory()->mobileUser()->create();

    $response = $this->get("/admin/users/{$target->id}/edit");

    $response->assertRedirect(route('login'));
});

test('viewing the edit page for a non-existent user returns a 404', function () {
    actingAsAdmin();

    $response = $this->get('/admin/users/999999/edit');

    $response->assertNotFound();
});

test('an admin can update another users name and email', function () {
    actingAsAdmin();
    $target = User::factory()->mobileUser()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

    $response = $this->put("/admin/users/{$target->id}", [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'password' => '',
        'password_confirmation' => '',
        'is_admin' => '0',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    expect($target->fresh()->name)->toBe('New Name');
    expect($target->fresh()->email)->toBe('new@example.com');
});

test('leaving the password blank keeps the current password', function () {
    actingAsAdmin();
    $target = User::factory()->mobileUser()->create();
    $originalPassword = $target->password;

    $response = $this->put("/admin/users/{$target->id}", [
        'name' => $target->name,
        'email' => $target->email,
        'password' => '',
        'password_confirmation' => '',
        'is_admin' => '0',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    expect($target->fresh()->password)->toBe($originalPassword);
});

test('updating a user with an overly long password is rejected and nothing changes', function () {
    actingAsAdmin();
    $target = User::factory()->mobileUser()->create();
    $originalPassword = $target->password;
    $overlyLongPassword = str_repeat('a', 256);

    $response = $this->put("/admin/users/{$target->id}", [
        'name' => $target->name,
        'email' => $target->email,
        'password' => $overlyLongPassword,
        'password_confirmation' => $overlyLongPassword,
        'is_admin' => '0',
    ]);

    $response->assertSessionHasErrors('password');
    expect($target->fresh()->password)->toBe($originalPassword);
});

test('an admin can grant admin access to a mobile user', function () {
    actingAsAdmin();
    $target = User::factory()->mobileUser()->create();

    $response = $this->put("/admin/users/{$target->id}", [
        'name' => $target->name,
        'email' => $target->email,
        'password' => '',
        'password_confirmation' => '',
        'is_admin' => '1',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    expect($target->fresh()->isAdmin())->toBeTrue();
});

test('an admin can revoke another admins access', function () {
    actingAsAdmin();
    $target = User::factory()->create();

    $response = $this->put("/admin/users/{$target->id}", [
        'name' => $target->name,
        'email' => $target->email,
        'password' => '',
        'password_confirmation' => '',
        'is_admin' => '0',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    expect($target->fresh()->isAdmin())->toBeFalse();
});

test('an admin cannot revoke their own admin access and nothing changes', function () {
    $admin = actingAsAdmin();

    $response = $this->put("/admin/users/{$admin->id}", [
        'name' => $admin->name,
        'email' => $admin->email,
        'password' => '',
        'password_confirmation' => '',
        'is_admin' => '0',
    ]);

    $response->assertSessionHasErrors('is_admin');
    expect($admin->fresh()->isAdmin())->toBeTrue();
});

test('updating a user with a duplicate email is rejected and nothing changes', function () {
    actingAsAdmin();
    User::factory()->create(['email' => 'taken@example.com']);
    $target = User::factory()->mobileUser()->create(['email' => 'target@example.com']);

    $response = $this->put("/admin/users/{$target->id}", [
        'name' => $target->name,
        'email' => 'taken@example.com',
        'password' => '',
        'password_confirmation' => '',
        'is_admin' => '0',
    ]);

    $response->assertSessionHasErrors('email');
    expect($target->fresh()->email)->toBe('target@example.com');
});

test('a mobile app user cannot update a user and nothing changes', function () {
    actingAsMobilePanelUser();
    $target = User::factory()->mobileUser()->create(['name' => 'Old Name']);

    $response = $this->put("/admin/users/{$target->id}", [
        'name' => 'New Name',
        'email' => $target->email,
        'password' => '',
        'password_confirmation' => '',
        'is_admin' => '1',
    ]);

    $response->assertForbidden();
    expect($target->fresh()->name)->toBe('Old Name');
    expect($target->fresh()->isAdmin())->toBeFalse();
});

test('an unauthenticated caller cannot update a user and nothing changes', function () {
    $target = User::factory()->mobileUser()->create(['name' => 'Old Name']);

    $response = $this->put("/admin/users/{$target->id}", [
        'name' => 'New Name',
        'email' => $target->email,
        'password' => '',
        'password_confirmation' => '',
        'is_admin' => '0',
    ]);

    $response->assertRedirect(route('login'));
    expect($target->fresh()->name)->toBe('Old Name');
});

test('updating a non-existent user returns a 404', function () {
    actingAsAdmin();

    $response = $this->put('/admin/users/999999', [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'password' => '',
        'password_confirmation' => '',
        'is_admin' => '0',
    ]);

    $response->assertNotFound();
});

test('an admin can delete another user', function () {
    actingAsAdmin();
    $target = User::factory()->mobileUser()->create();

    $response = $this->delete("/admin/users/{$target->id}");

    $response->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseMissing('wms_users', ['id' => $target->id]);
});

test('deleting a user redirects back with the current page preserved', function () {
    actingAsAdmin();
    $target = User::factory()->mobileUser()->create();

    $response = $this->delete("/admin/users/{$target->id}?page=2");

    $response->assertRedirect(route('admin.users.index', ['page' => 2]));
});

test('an admin cannot delete their own account and nothing changes', function () {
    $admin = actingAsAdmin();

    $response = $this->delete("/admin/users/{$admin->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('wms_users', ['id' => $admin->id]);
});

test('a mobile app user cannot delete a user and nothing changes', function () {
    actingAsMobilePanelUser();
    $target = User::factory()->mobileUser()->create();

    $response = $this->delete("/admin/users/{$target->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('wms_users', ['id' => $target->id]);
});

test('an unauthenticated caller cannot delete a user and nothing changes', function () {
    $target = User::factory()->mobileUser()->create();

    $response = $this->delete("/admin/users/{$target->id}");

    $response->assertRedirect(route('login'));
    $this->assertDatabaseHas('wms_users', ['id' => $target->id]);
});

test('deleting a non-existent user returns a 404', function () {
    actingAsAdmin();

    $response = $this->delete('/admin/users/999999');

    $response->assertNotFound();
});
