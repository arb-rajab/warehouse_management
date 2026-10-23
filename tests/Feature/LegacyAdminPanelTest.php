<?php

use App\Http\Controllers\RepresentativePaymentController;
use App\Models\StoreUser;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Regression coverage for the legacy store's admin panel under /temp/admin,
 * which no store admin could use at all. In the order a request meets them:
 *
 * - `/temp/admin` itself was swallowed by the storefront's `/{slug}`
 *   catch-all (see RouteServiceProviderTest), and logging in sent an admin to
 *   this app's own `/admin` instead;
 * - `auth` ran ahead of UseStoreAuthGuard (it is in the framework's
 *   middleware priority list), so it checked this app's own guard and sent
 *   every signed-in store admin back to the login page;
 * - every page gated by spatie's `permission:` middleware (198 routes) failed
 *   on the alias bootstrap/app.php never registered;
 * - the layout read `users.raizer_admin`, a column the store's `users` table
 *   lacked, and `Language::first()->rtl` with no language row;
 * - the dashboard iterated a `total_visits` setting nothing here creates.
 */
test('a store admin logging in is sent to the store admin dashboard', function () {
    $admin = createStoreAdmin();

    $this->post('/temp/login', ['email' => $admin->email, 'password' => 'correct-password'])
        ->assertRedirect(route('shop.admin.dashboard'));
});

test('a staff member holding admin_dashboard logging in is sent to the store admin dashboard', function () {
    $staff = createStoreStaff('admin_dashboard');

    $this->post('/temp/login', ['email' => $staff->email, 'password' => 'correct-password'])
        ->assertRedirect(route('shop.admin.dashboard'));

    $this->get(route('shop.admin.dashboard'))->assertOk();
});

test('a staff member without admin_dashboard logging in lands on their profile page, not a 403', function () {
    $staff = createStoreStaff('view_all_orders');
    $noPermissions = createStoreStaff();

    foreach ([$staff, $noPermissions] as $user) {
        $this->post('/temp/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertRedirect(route('profile.index'));

        $this->get(route('profile.index'))->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Store/Admin/Profile/Index')->where('profile.id', $user->id)
        );
        $this->get(route('shop.admin.dashboard'))->assertForbidden();

        $this->post('/temp/logout');
        $this->flushSession();
        app('auth')->forgetGuards();
    }
});

test('logging in as a seller or a customer still lands where it did', function () {
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');

    $this->post('/temp/login', ['email' => $seller->email, 'password' => 'correct-password'])
        ->assertRedirect(route('seller.dashboard'));

    $this->post('/temp/logout');
    $this->flushSession();
    app('auth')->forgetGuards();

    $this->post('/temp/login', ['email' => $customer->email, 'password' => 'correct-password'])
        ->assertRedirect(route('dashboard'));
});

test('a permission-gated admin page renders for a Super Admin', function () {
    actingAsStoreUser(createStoreAdmin());

    $this->get('/temp/admin/brands')->assertOk();
});

test('a permission-gated admin page refuses an admin without that permission', function () {
    actingAsStoreUser(createStoreUser('admin'));

    $this->get('/temp/admin/brands')->assertForbidden();
});

/**
 * The legacy repo's own User -> StoreUser rename wrote `StoreStoreUser` here,
 * a class that does not exist. The page itself can't render yet (the store
 * layout reads the `visits` table, which has no migration here), so this
 * calls the action directly, the way LegacyControllerMiddlewareConversionTest
 * does.
 */
test('the representative payment form offers the customers who are not representatives', function () {
    $customer = createStoreUser('customer');
    $representative = createStoreUser('customer');
    $representative->forceFill(['is_rep' => true])->save();
    createStoreUser('seller');

    $customers = (new RepresentativePaymentController)->create()->getData()['customers'];

    expect($customers->pluck('id')->all())->toBe([$customer->id]);
});
