<?php

use App\Http\Controllers\RepresentativePaymentController;
use App\Models\Role;
use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;

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
 * The staff-role screen lists permissions through the legacy
 * App\Models\Permission model, which read a `permissions` table that does not
 * exist here: both apps' permissions live in spatie's `wms_permissions`.
 */
test('the staff role screen lists the permissions, grouped by section', function () {
    Permission::create(['name' => 'approve_bulk_price_changes', 'guard_name' => 'store', 'section' => 'price_changes']);
    actingAsStoreUser(createStoreAdmin());

    $this->get('/temp/admin/roles/create')
        ->assertOk()
        ->assertSeeInOrder(['Price Changes', 'Approve Bulk Price Changes']);
});

/**
 * The role screens keyed a role's default-language translation on
 * env('DEFAULT_LANGUAGE'), which is null once `config:cache` has run (every
 * deploy does): creating a role then failed on role_translations.lang NOT
 * NULL, and renaming one in the default language compared 'en' with null and
 * changed nothing. It is config('app.default_language') now.
 */
test('creating a staff role saves it with its default-language translation', function () {
    actingAsStoreUser(createStoreAdmin());

    $this->post('/temp/admin/roles', ['name' => 'Clerk'])->assertRedirect(route('roles.index'));

    $role = Role::where('name', 'Clerk')->firstOrFail();
    expect($role->guard_name)->toBe('store');
    $this->assertDatabaseHas('role_translations', ['role_id' => $role->id, 'name' => 'Clerk', 'lang' => 'en']);
});

test('renaming a staff role in the default language renames the role', function () {
    $role = SpatieRole::findOrCreate('Clerk', 'store');
    actingAsStoreUser(createStoreAdmin());

    $this->put("/temp/admin/roles/{$role->id}", ['name' => 'Senior Clerk', 'lang' => 'en'])->assertRedirect();

    $this->assertDatabaseHas('wms_roles', ['id' => $role->id, 'name' => 'Senior Clerk']);
    $this->assertDatabaseHas('role_translations', ['role_id' => $role->id, 'name' => 'Senior Clerk', 'lang' => 'en']);
});

test('renaming a staff role in another language leaves the role name alone', function () {
    $role = SpatieRole::findOrCreate('Clerk', 'store');
    actingAsStoreUser(createStoreAdmin());

    $this->put("/temp/admin/roles/{$role->id}", ['name' => 'Employé', 'lang' => 'fr'])->assertRedirect();

    $this->assertDatabaseHas('wms_roles', ['id' => $role->id, 'name' => 'Clerk']);
    $this->assertDatabaseHas('role_translations', ['role_id' => $role->id, 'name' => 'Employé', 'lang' => 'fr']);
});

test('a customer cannot create or rename a staff role', function () {
    $role = SpatieRole::findOrCreate('Clerk', 'store');
    actingAsStoreUser(createStoreUser('customer'));

    $this->post('/temp/admin/roles', ['name' => 'Nope'])->assertNotFound();
    $this->put("/temp/admin/roles/{$role->id}", ['name' => 'Hijacked', 'lang' => 'en'])->assertNotFound();

    $this->assertDatabaseMissing('wms_roles', ['name' => 'Nope']);
    $this->assertDatabaseHas('wms_roles', ['id' => $role->id, 'name' => 'Clerk']);
    $this->assertDatabaseCount('role_translations', 0);
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
