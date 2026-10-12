<?php

use App\Http\Controllers\RepresentativePaymentController;
use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

test('the store admin dashboard renders for a signed-in store admin', function () {
    $admin = actingAsStoreUser(createStoreAdmin());

    $this->get('/temp/admin')
        ->assertOk()
        ->assertSee($admin->name);
});

test("the admin dashboard shows the admin's unread notifications", function () {
    $admin = createStoreAdmin();
    $other = createStoreAdmin();

    foreach ([[$admin, 'ORD-MINE'], [$other, 'ORD-THEIRS']] as [$notifiable, $orderCode]) {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\OrderNotification',
            'notifiable_type' => StoreUser::class,
            'notifiable_id' => $notifiable->id,
            'data' => json_encode(['order_id' => 1, 'order_code' => $orderCode, 'user_id' => 1, 'seller_id' => 1, 'status' => 'placed']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    actingAsStoreUser($admin);

    $this->get('/temp/admin')
        ->assertOk()
        ->assertSee('ORD-MINE')
        ->assertDontSee('ORD-THEIRS');
});

test('a permission-gated admin page renders for a Super Admin', function () {
    actingAsStoreUser(createStoreAdmin());

    $this->get('/temp/admin/brands')->assertOk();
});

test('a permission-gated admin page refuses an admin without that permission', function () {
    actingAsStoreUser(createStoreUser('admin'));

    $this->get('/temp/admin/brands')->assertForbidden();
});

test('the store admin panel is not found for a customer', function () {
    actingAsStoreUser(createStoreUser('customer'));

    $this->get('/temp/admin')->assertNotFound();
});

/**
 * RaizerSuperAdmin returned null for anyone but the vendor's own super admin,
 * which Laravel 8 rendered as an empty page and this app's own typed web
 * middleware turned into a 500.
 */
test('a vendor-only admin page refuses an admin who is not the vendor super admin', function () {
    actingAsStoreUser(createStoreAdmin());

    $this->get('/temp/admin/activation')->assertForbidden();
});

test('a vendor-only admin page renders for the vendor super admin', function () {
    actingAsStoreUser(createStoreAdmin(raizerAdmin: true));

    $this->get('/temp/admin/activation')->assertOk();
});

test('the vendor super admin gets the cache-clear button', function () {
    actingAsStoreUser(createStoreAdmin(raizerAdmin: true));

    $this->get('/temp/admin')->assertSee(route('cache.clear'));
});

test('an admin who is not the vendor super admin gets no cache-clear button', function () {
    actingAsStoreUser(createStoreAdmin());

    $this->get('/temp/admin')->assertDontSee(route('cache.clear'));
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
