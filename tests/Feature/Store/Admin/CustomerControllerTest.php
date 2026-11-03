<?php

use App\Models\Address;
use App\Models\CustomerProduct;
use App\Models\Product;
use App\Models\StoreUser;
use App\Notifications\TermsAgreementNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

/** @return array<string,mixed> */
function storeCustomerForm(array $overrides = []): array
{
    if (! DB::table('countries')->where('name', 'Netherlands')->exists()) {
        DB::table('countries')->insert(['name' => 'Netherlands', 'code' => 'NL', 'status' => 1]);
    }

    return ['company_name' => 'Acme', 'country' => 'Netherlands', 'tax_number' => 'NL123', 'bank_account_number' => 'NL123', 'company_address' => 'Company address', 'shipping_address' => 'Shipping address', 'trade_license' => UploadedFile::fake()->createWithContent('license.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n"), 'name' => 'New Customer', 'email' => 'new-customer@example.com', 'phone' => '31600000001', 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password', ...$overrides];
}

foreach (['customers.index' => 'Store/Admin/Customers/Customers/Index', 'customers.archive' => 'Store/Admin/Customers/Customers/Index', 'representatives.index' => 'Store/Admin/Customers/Customers/Index', 'customers.create' => 'Store/Admin/Customers/Customers/Create', 'representatives.create' => 'Store/Admin/Customers/Customers/Create'] as $route => $component) {
    test($route.' renders its Inertia screen', function () use ($route, $component) {
        actingAsStoreUser(createStoreAdmin());
        $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
    });
    test($route.' refuses unauthorized callers', function (string $caller) use ($route) {
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get(route($route)), $caller);
    })->with('refused store admin callers');
}
foreach (['customers.edit' => 'Store/Admin/Customers/Customers/Edit', 'customers.page-views' => 'Store/Admin/Customers/Customers/Views'] as $route => $component) {
    test($route.' renders its Inertia screen', function () use ($route, $component) {
        actingAsStoreUser(createStoreAdmin());
        $customer = createStoreUser();
        $this->get(route($route, $customer))->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
    });
}
foreach (['customers.edit', 'customers.page-views', 'change_password', 'customers.whatsapp_contact', 'customers.login'] as $route) {
    test($route.' refuses unauthorized callers', function (string $caller) use ($route) {
        $customer = createStoreUser();
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get(route($route, $customer)), $caller);
    })->with('refused store admin callers');
    test($route.' rejects missing and privileged accounts', function () use ($route) {
        actingAsStoreUser(createStoreAdmin());
        $seller = createStoreUser('seller');
        $this->get(route($route, 999999))->assertNotFound();
        $this->get(route($route, $seller))->assertForbidden();
    });
}

test('customer list returns a narrow full record without representatives sellers or archived accounts', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    $customer = createStoreUser('customer', 'amy@example.com');
    $customer->forceFill(['name' => 'Amy', 'phone' => '31611111111', 'company_name' => 'Amy Company', 'admin_verified' => 1, 'is_important' => 1, 'activity_status' => 'new_user'])->save();
    createStoreUser('seller');
    $rep = createStoreUser();
    $rep->forceFill(['is_rep' => true])->save();
    $archived = createStoreUser();
    $archived->forceFill(['is_delete' => 1])->save();
    $this->get(route('customers.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->has('customers.data.0', fn (Assert $record) => $record
        ->where('id', $customer->id)->where('name', 'Amy')->where('email', 'amy@example.com')->where('phone', '31611111111')->where('company_name', 'Amy Company')->where('is_rep', false)->where('archived', false)->where('banned', false)->where('verified', true)->where('important', true)->where('activity_status', 'new_user')->where('rep_id', null)->where('rep_serial', null)->etc()));
});
test('blank yes/no filters from the Filters dialog mean all and are not applied as No', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    $customer = createStoreUser('customer', 'blank-filters@example.com');
    $customer->forceFill(['is_important' => 1, 'admin_verified' => 0])->save();
    $this->get(route('customers.index', ['important' => '1', 'verified' => '', 'from_api' => '', 'registration_completed' => '', 'activity_status' => '']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('filters.important', true)->missing('filters.verified')->missing('filters.from_api')->missing('filters.registration_completed'));
    $this->get(route('customers.index', ['verified' => '0']))
        ->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('filters.verified', false));
});
foreach (['customers.index', 'representatives.index', 'customers.archive'] as $route) {
    test($route.' paginates validates and filters', function () use ($route) {
        actingAsStoreUser(createStoreAdmin());
        foreach (range(1, 23) as $number) {
            $user = createStoreUser();
            $user->forceFill(['is_rep' => $route === 'representatives.index', 'is_delete' => $route === 'customers.archive' ? 1 : null])->save();
        }
        $this->get(route($route))->assertInertia(fn (Assert $page) => $page->has('customers.data', 20)->where('customers.meta.total', 23));
        $this->get(route($route, ['per_page' => 10]))->assertInertia(fn (Assert $page) => $page->has('customers.data', 10)->where('filters.per_page', 10));
        $this->get(route($route, ['page' => 2]))->assertInertia(fn (Assert $page) => $page->has('customers.data', 3));
        $this->get(route($route, ['per_page' => 7]))->assertSessionHasErrors('per_page');
        $this->get(route($route, ['search' => 'missing']))->assertInertia(fn (Assert $page) => $page->has('customers.data', 0));
    });
}
test('read-only customer staff cannot request the archive filter', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    $this->get(route('customers.index', ['archived' => 1]))->assertForbidden();
});

foreach (['customers.store' => false, 'representatives.store' => true] as $route => $representative) {
    test($route.' creates an account without forged privileges', function () use ($route, $representative) {
        actingAsStoreUser(createStoreStaff('edit_customer'));
        $this->post(route($route), storeCustomerForm(['user_type' => 'admin', 'is_rep' => ! $representative, 'admin_verified' => 1, 'rep_id' => '99', 'verification_code' => '000']))->assertRedirect(route($representative ? 'representatives.index' : 'customers.index'));
        $user = StoreUser::query()->where('email', 'new-customer@example.com')->firstOrFail();
        expect($user->user_type)->toBe('customer')->and($user->is_rep)->toBe($representative)->and((bool) $user->admin_verified)->toBe($representative)->and($user->rep_id)->toBeNull()->and($user->verification_code)->toBeNull()->and(Hash::check('a-long-password', $user->password))->toBeTrue();
    });
    test($route.' refuses unauthorized writes and changes nothing', function (string $caller) use ($route) {
        actingAsRefusedStoreCaller($caller);
        $before = StoreUser::count();
        assertStoreCallerRefused($this->post(route($route), storeCustomerForm()), $caller);
        expect(StoreUser::count())->toBe($before);
    })->with('refused store admin callers');
    test($route.' validates input and ignores a read-only permission', function () use ($route) {
        actingAsStoreUser(createStoreStaff('edit_customer'));
        $this->post(route($route), storeCustomerForm(['name' => '', 'email' => 'bad', 'password' => 'short', 'password_confirmation' => 'wrong']))->assertSessionHasErrors(['name', 'email', 'password']);
        $taken = createStoreUser();
        $this->post(route($route), storeCustomerForm(['email' => $taken->email, 'phone' => $taken->phone]))->assertSessionHasErrors(['email', 'phone']);
        $this->post(route($route), storeCustomerForm(['trade_license' => UploadedFile::fake()->create('script.html', 1, 'text/html')]))->assertSessionHasErrors('trade_license');
        actingAsStoreUser(createStoreStaff('view_all_customers'));
        $before = StoreUser::count();
        $this->post(route($route), storeCustomerForm())->assertForbidden();
        expect(StoreUser::count())->toBe($before)->and(StoreUser::where('email', 'new-customer@example.com')->exists())->toBeFalse();
    });
}

foreach (['customers.update', 'customers.destroy', 'customers.restore', 'customers.verify', 'customers.ban', 'customers.important', 'customers.not_important', 'customers.change_activity_status', 'customers.update_customer_notes', 'update_password', 'customers.sendAgreement'] as $route) {
    test($route.' refuses unauthorized callers and preserves the record', function (string $caller) use ($route) {
        $customer = createStoreUser();
        actingAsRefusedStoreCaller($caller);
        $before = $customer->fresh()->getAttributes();
        $payload = storeCustomerForm(['new_status' => 'has_been_contacted', 'admin_notes' => 'changed']);
        $response = $route === 'customers.destroy' ? $this->delete(route($route, $customer), $payload) : $this->post(route($route, $customer), $payload);
        assertStoreCallerRefused($response, $caller);
        expect($customer->fresh()->getAttributes())->toBe($before);
    })->with('refused store admin callers');
    test($route.' returns 404 for missing accounts', function () use ($route) {
        actingAsStoreUser(createStoreAdmin());
        $payload = storeCustomerForm(['new_status' => 'new_user']);
        ($route === 'customers.destroy' ? $this->delete(route($route, 999999), $payload) : $this->post(route($route, 999999), $payload))->assertNotFound();
    });
    test($route.' cannot write a seller or staff account even as Super Admin', function () use ($route) {
        actingAsStoreUser(createStoreAdmin());
        $seller = createStoreUser('seller');
        $before = $seller->getAttributes();
        $payload = storeCustomerForm(['new_status' => 'new_user']);
        ($route === 'customers.destroy' ? $this->delete(route($route, $seller), $payload) : $this->post(route($route, $seller), $payload))->assertForbidden();
        expect($seller->fresh()->getAttributes())->toBe($before);
    });
}

test('profile writes validate uniqueness password confirmation and persist safe fields only', function () {
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $customer = createStoreUser();
    $noise = createStoreUser();
    $this->post(route('customers.update', $customer), storeCustomerForm(['email' => $noise->email, 'phone' => $noise->phone]))->assertSessionHasErrors(['email', 'phone']);
    $this->post(route('customers.update', $customer), storeCustomerForm(['password_confirmation' => 'different']))->assertSessionHasErrors('password');
    $this->post(route('customers.update', $customer), storeCustomerForm(['user_type' => 'staff', 'is_rep' => 1, 'admin_verified' => 1]))->assertRedirect(route('representatives.index'));
    expect($customer->fresh()->name)->toBe('New Customer')->and($customer->fresh()->user_type)->toBe('customer')->and($customer->fresh()->is_rep)->toBeTrue()->and($noise->fresh()->name)->not->toBe('New Customer');
    $this->post(route('update_password', $customer), ['password' => 'tiny', 'password_confirmation' => 'tiny'])->assertSessionHasErrors('password');
    $this->post(route('update_password', $customer), ['password' => 'another-password', 'password_confirmation' => 'another-password'])->assertRedirect(route('representatives.index'));
    expect(Hash::check('another-password', $customer->fresh()->password))->toBeTrue();
});
test('customer status importance notes ban archive and restore actions preserve domain invariants', function () {
    actingAsStoreUser(createStoreAdmin());
    $customer = createStoreUser(verified: false);
    $customer->forceFill(['last_order_at' => now()])->save();
    $this->post(route('customers.verify', $customer))->assertRedirect();
    expect($customer->fresh()->admin_verified)->toBeTrue()->and($customer->fresh()->admin_verified_at)->not->toBeNull();
    $this->post(route('customers.verify', $customer))->assertRedirect();
    expect($customer->fresh()->admin_verified)->toBeFalse()->and($customer->fresh()->admin_verified_at)->toBeNull()->and($customer->fresh()->last_order_at)->toBeNull();
    $this->post(route('customers.important', $customer))->assertRedirect();
    expect((bool) $customer->fresh()->is_important)->toBeTrue();
    $this->post(route('customers.not_important', $customer))->assertRedirect();
    expect((bool) $customer->fresh()->is_important)->toBeFalse();
    $this->post(route('customers.ban', $customer))->assertRedirect();
    expect((bool) $customer->fresh()->banned)->toBeTrue();
    $this->post(route('customers.ban', $customer))->assertRedirect();
    expect((bool) $customer->fresh()->banned)->toBeFalse();
    $this->post(route('customers.change_activity_status', $customer), ['new_status' => 'invalid'])->assertSessionHasErrors('new_status');
    $this->post(route('customers.change_activity_status', $customer), ['new_status' => 'has_been_contacted'])->assertRedirect();
    expect($customer->fresh()->activity_status)->toBe('has_been_contacted');
    $this->post(route('customers.update_customer_notes', $customer), ['admin_notes' => str_repeat('a', 256)])->assertSessionHasErrors('admin_notes');
    $this->post(route('customers.update_customer_notes', $customer), ['admin_notes' => 'Contact later'])->assertRedirect();
    expect($customer->fresh()->admin_notes)->toBe('Contact later');
    $this->delete(route('customers.destroy', $customer))->assertRedirect();
    expect((bool) $customer->fresh()->is_delete)->toBeTrue();
    $this->post(route('customers.restore', $customer))->assertRedirect();
    expect($customer->fresh()->is_delete)->toBeNull();
});
test('bulk archive validates all targets before writing and never deletes classified products', function () {
    actingAsStoreUser(createStoreAdmin());
    $customer = createStoreUser();
    $seller = createStoreUser('seller');
    $classified = CustomerProduct::forceCreate(['user_id' => $customer->id, 'name' => 'Customer listing', 'published' => 1, 'status' => 1]);
    $this->post(route('bulk-customer-delete'), ['id' => [$customer->id, $seller->id]])->assertSessionHasErrors('id.1');
    expect($customer->fresh()->is_delete)->toBeNull();
    $this->post(route('bulk-customer-delete'), ['id' => [$customer->id]])->assertRedirect();
    expect((bool) $customer->fresh()->is_delete)->toBeTrue()->and(CustomerProduct::whereKey($classified->id)->exists())->toBeTrue()->and($classified->fresh()->published)->toBe(1);
});
test('bulk archive refuses unauthorized callers without changing accounts', function (string $caller) {
    $customer = createStoreUser();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post(route('bulk-customer-delete'), ['id' => [$customer->id]]), $caller);
    expect($customer->fresh()->is_delete)->toBeNull();
})->with('refused store admin callers');
test('terms agreement enforces completeness without sending on validation failure', function () {
    Notification::fake();
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $customer = createStoreUser();
    $this->post(route('customers.sendAgreement', $customer))->assertSessionHasErrors('agreement');
    Notification::assertNothingSent();
    $customer->forceFill(['bank_account_number' => 'NL123', 'trade_license' => 'license.pdf', 'company_address' => 'Company', 'shipping_address' => 'Shipping', 'tax_number' => '123'])->save();
    $this->post(route('customers.sendAgreement', $customer))->assertRedirect();
    Notification::assertSentTo($customer, TermsAgreementNotification::class);
});
test('login-as switches only the store guard and refuses banned archived accounts', function () {
    $staff = createStoreStaff('login_as_customer');
    actingAsStoreUser($staff);
    $customer = createStoreUser();
    $customer->forceFill(['banned' => 1])->save();
    $this->get(route('customers.login', $customer))->assertForbidden();
    expect(auth('store')->id())->toBe($staff->id);
    $customer->forceFill(['banned' => 0, 'is_delete' => 1])->save();
    $this->get(route('customers.login', $customer))->assertForbidden();
    $customer->forceFill(['is_delete' => null])->save();
    $this->get(route('customers.login', $customer))->assertRedirect(route('dashboard'));
    expect(auth('store')->id())->toBe($customer->id)->and(auth('web')->id())->toBeNull();
});
test('licence download refuses unauthorized callers', function (string $caller) {
    Storage::fake('local');
    Storage::disk('local')->put('uploads/licenses/license.pdf', 'private');
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('download.trade_license', 'license.pdf')), $caller);
})->with('refused store admin callers');
test('page views aggregate counts exclude other customers and paginate grouped rows', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    $customer = createStoreUser();
    $noise = createStoreUser();
    $ids = [];
    foreach (range(1, 23) as $number) {
        $product = Product::factory()->create(['name' => 'Viewed product '.$number, 'slug' => 'viewed-product-'.$number]);
        $ids[] = $product->id;
        DB::table('user_page_views')->insert(['user_id' => $customer->id, 'viewable_type' => Product::class, 'viewable_id' => $product->id, 'viewed_at' => '2026-10-08 12:00:00']);
    }
    DB::table('user_page_views')->insert(['user_id' => $customer->id, 'viewable_type' => Product::class, 'viewable_id' => $ids[0], 'viewed_at' => '2026-10-08 13:00:00']);
    DB::table('user_page_views')->insert(['user_id' => $noise->id, 'viewable_type' => Product::class, 'viewable_id' => $ids[0], 'viewed_at' => '2026-10-08 14:00:00']);
    $this->get(route('customers.page-views', $customer))->assertInertia(fn (Assert $page) => $page->has('views.data', 20)->where('views.meta.total', 23)->where('views.data.0.view_count', 2)->where('views.data.0', ['id' => $ids[0], 'kind' => 'products', 'name' => 'Viewed product 1', 'slug' => 'viewed-product-1', 'public_url' => route('product', 'viewed-product-1', false), 'view_count' => 2, 'last_viewed_at' => '2026-10-08 13:00:00']));
    $this->get(route('customers.page-views', [$customer, 'per_page' => 7]))->assertSessionHasErrors('per_page');
});

test('every customer write rejects staff with only the old read permission', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    $customer = createStoreUser();
    $before = $customer->getAttributes();
    foreach (['customers.update', 'customers.verify', 'customers.important', 'customers.not_important', 'customers.change_activity_status', 'customers.update_customer_notes', 'update_password', 'customers.sendAgreement'] as $route) {
        $this->post(route($route, $customer), storeCustomerForm(['new_status' => 'has_been_contacted', 'admin_notes' => 'Changed']))->assertForbidden();
        expect($customer->fresh()->getAttributes())->toBe($before);
    }
});

test('representative customer creation stays server-rendered and usable without admin read permissions', function () {
    Storage::fake('local');
    $representative = createStoreUser();
    $representative->forceFill(['is_rep' => true])->save();
    actingAsStoreUser($representative);
    $this->get(route('create_customer'))->assertOk()->assertViewIs('shop.frontend.user.create_user');
    $this->post(route('store_customer'), storeCustomerForm(['user_type' => 'admin', 'is_rep' => true]))->assertRedirect(route('dashboard'));
    $created = StoreUser::where('email', 'new-customer@example.com')->firstOrFail();
    expect($created->user_type)->toBe('customer')->and($created->is_rep)->toBeFalse()->and($created->trade_license)->toMatch('/^[a-zA-Z0-9]{40}\.pdf$/');
    Storage::disk('local')->assertExists('uploads/licenses/'.$created->trade_license);
    expect(Address::where('user_id', $created->id)->count())->toBe(1);
});

test('ordinary customers cannot create other customer accounts through representative workflow', function () {
    actingAsStoreUser(createStoreUser());
    $before = StoreUser::count();
    $this->post(route('store_customer'), storeCustomerForm())->assertForbidden();
    expect(StoreUser::count())->toBe($before);
});

test('customer WhatsApp contacts retain international formatting and validate missing or invalid numbers', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    $customer = createStoreUser();
    $customer->phone = '0612345678';
    $customer->save();
    $this->get(route('customers.whatsapp_contact', $customer))->assertRedirect('https://wa.me/31612345678');
    $customer->phone = null;
    $customer->save();
    $this->get(route('customers.whatsapp_contact', $customer))->assertSessionHasErrors(['phone' => __('messages.customer_phone_required')]);
    $customer->phone = 'invalid';
    $customer->save();
    $this->get(route('customers.whatsapp_contact', $customer))->assertSessionHasErrors(['phone' => __('messages.customer_phone_invalid')]);
});

test('customer trade licences preserve the twenty megabyte limit and stay on private storage', function () {
    Storage::fake('local');
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $licence = UploadedFile::fake()->createWithContent('large-licence.pdf', "%PDF-1.4\n".str_repeat(' ', 3 * 1024 * 1024)."\n%%EOF\n");
    $this->post(route('customers.store'), storeCustomerForm(['trade_license' => $licence]))->assertRedirect(route('customers.index'));
    $customer = StoreUser::where('email', 'new-customer@example.com')->firstOrFail();
    Storage::disk('local')->assertExists('uploads/licenses/'.$customer->trade_license);
    $this->post(route('customers.update', $customer), storeCustomerForm(['trade_license' => UploadedFile::fake()->create('too-large.pdf', 20001, 'application/pdf')]))->assertSessionHasErrors('trade_license');
    expect($customer->fresh()->trade_license)->toBe($customer->trade_license);
});

test('customer edit props contain only the complete editable profile and customer-owned addresses', function () {
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $customer = createStoreUser('customer', 'profile@example.com');
    $customer->forceFill(['name' => 'Profile Customer', 'phone' => '31612345678', 'company_name' => 'Acme', 'country' => 'Netherlands', 'tax_number' => 'VAT123', 'bank_account_number' => 'NL123', 'company_address' => 'Company Road', 'shipping_address' => 'Shipping Road', 'member_serial' => '42', 'AccSysID' => 42, 'admin_notes' => 'Call later', 'admin_verified' => 1, 'registration_completed' => 0])->save();
    $props = $this->get(route('customers.edit', $customer))->assertOk()->inertiaProps();
    expect($props['customer'])->toEqual([
        'id' => $customer->id, 'name' => 'Profile Customer', 'email' => 'profile@example.com', 'phone' => '31612345678', 'company_name' => 'Acme',
        'is_rep' => false, 'archived' => false, 'banned' => false, 'verified' => true, 'important' => true, 'activity_status' => 'new_user', 'rep_id' => null, 'rep_serial' => null,
        'country' => 'Netherlands', 'tax_number' => 'VAT123', 'bank_account_number' => 'NL123', 'company_address' => 'Company Road', 'shipping_address' => 'Shipping Road', 'member_serial' => '42', 'AccSysID' => 42, 'trade_license' => null, 'admin_notes' => 'Call later', 'registration_completed' => false,
    ])->and($props['addresses'])->toBe([]);
});

test('customer filters preserve the important default and distinguish verified app registration and activity status', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    $customer = createStoreUser();
    $customer->forceFill(['is_important' => 0, 'admin_verified' => 1, 'from_api' => 1, 'registration_completed' => 1, 'activity_status' => 'has_been_contacted'])->save();
    $noise = createStoreUser();
    $this->get(route('customers.index'))->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.id', $noise->id)->where('filters.important', true));
    $this->get(route('customers.index', ['important' => 0, 'verified' => 1, 'from_api' => 1, 'registration_completed' => 1, 'activity_status' => 'has_been_contacted', 'archived' => 0]))->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.id', $customer->id)->where('filters.important', false)->where('filters.archived', false));
});

test('authorized customer edits preserve representative conversion and its existing assignment data', function () {
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $customer = createStoreUser();
    $customer->forceFill(['rep_package_id' => 42, 'rep_discount_percentage' => 10])->save();
    $this->post(route('customers.update', $customer), storeCustomerForm(['is_rep' => 'invalid']))->assertSessionHasErrors('is_rep');
    expect($customer->fresh()->is_rep)->toBeFalse();
    $this->post(route('customers.update', $customer), storeCustomerForm(['is_rep' => 1, 'rep_package_id' => 99, 'rep_discount_percentage' => 99]))->assertRedirect(route('representatives.index'));
    expect($customer->fresh()->is_rep)->toBeTrue()->and($customer->fresh()->rep_package_id)->toBe(42)->and((float) $customer->fresh()->rep_discount_percentage)->toBe(10.0);
    $this->post(route('customers.update', $customer), storeCustomerForm(['is_rep' => 0]))->assertRedirect(route('customers.index'));
    expect($customer->fresh()->is_rep)->toBeFalse()->and($customer->fresh()->rep_package_id)->toBe(42);
});

test('customer create requires the seven route-dependent business fields', function (string $field) {
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $before = StoreUser::count();
    $this->post(route('customers.store'), storeCustomerForm([$field => null]))->assertSessionHasErrors($field);
    expect(StoreUser::count())->toBe($before);
})->with(['company_name', 'country', 'tax_number', 'bank_account_number', 'company_address', 'shipping_address', 'trade_license']);

test('customer edit permits empty business fields and persists the allowed profile update without a license', function () {
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $customer = createStoreUser();
    $optional = array_fill_keys(['company_name', 'country', 'tax_number', 'bank_account_number', 'company_address', 'shipping_address'], null);
    $customer->forceFill(array_fill_keys(array_keys($optional), 'Previous value'))->save();
    $payload = storeCustomerForm([...$optional, 'trade_license' => null]);
    $this->post(route('customers.update', $customer), $payload)->assertSessionHasNoErrors()->assertRedirect(route('customers.index'));
    $customer->refresh();
    foreach ($optional as $field => $value) {
        expect($customer->getAttribute($field))->toBe($field === 'company_name' ? '' : null);
    }
    expect($customer->name)->toBe($payload['name'])->and($customer->trade_license)->toBeNull();
});

test('customer edit retains a company name when it is not submitted', function () {
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $customer = createStoreUser();
    $customer->forceFill(['company_name' => 'Retained Company'])->save();
    $payload = storeCustomerForm(['trade_license' => null]);
    unset($payload['company_name']);
    $this->post(route('customers.update', $customer), $payload)->assertSessionHasNoErrors()->assertRedirect(route('customers.index'));
    expect($customer->fresh()->company_name)->toBe('Retained Company');
});

test('customer and representative create refuse a tax number another customer already has', function (string $route) {
    actingAsStoreUser(createStoreAdmin());
    $holder = createStoreUser();
    $holder->forceFill(['tax_number' => 'NL123'])->save();
    $before = StoreUser::count();

    $this->post(route($route), storeCustomerForm(['tax_number' => 'NL123']))
        ->assertSessionHasErrors(['tax_number' => __('validation.custom.tax_number.unique')]);

    expect(StoreUser::count())->toBe($before)
        ->and(StoreUser::where('tax_number', 'NL123')->pluck('id')->all())->toBe([$holder->id]);
})->with(['customers.store', 'representatives.store']);

test('customer create stores a free tax number trimmed', function () {
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $noise = createStoreUser();
    $noise->forceFill(['tax_number' => 'NL999'])->save();

    $this->post(route('customers.store'), storeCustomerForm(['tax_number' => '  NL123  ']))->assertSessionHasNoErrors()->assertRedirect(route('customers.index'));

    expect(StoreUser::where('email', 'new-customer@example.com')->value('tax_number'))->toBe('NL123')
        ->and($noise->fresh()->tax_number)->toBe('NL999');
});

test('customer edit keeps the customer\'s own tax number and refuses another customer\'s without changing anything', function () {
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $customer = createStoreUser();
    $customer->forceFill(['name' => 'Before', 'tax_number' => 'NL-OWN'])->save();
    $other = createStoreUser();
    $other->forceFill(['tax_number' => 'NL-OTHER'])->save();

    $this->post(route('customers.update', $customer), storeCustomerForm(['tax_number' => 'NL-OTHER', 'trade_license' => null]))
        ->assertSessionHasErrors(['tax_number' => __('validation.custom.tax_number.unique')]);
    expect($customer->fresh())->name->toBe('Before')->tax_number->toBe('NL-OWN')
        ->and($other->fresh()->tax_number)->toBe('NL-OTHER');

    $this->post(route('customers.update', $customer), storeCustomerForm(['tax_number' => 'NL-OWN', 'trade_license' => null]))
        ->assertSessionHasNoErrors()->assertRedirect(route('customers.index'));
    expect($customer->fresh())->name->toBe('New Customer')->tax_number->toBe('NL-OWN');
});

test('two customers can both be left without a tax number', function () {
    actingAsStoreUser(createStoreStaff('edit_customer'));
    $first = createStoreUser();
    $second = createStoreUser();
    $first->forceFill(['tax_number' => 'NL-FIRST'])->save();
    $second->forceFill(['tax_number' => 'NL-SECOND'])->save();

    foreach ([$first, $second] as $index => $customer) {
        $this->post(route('customers.update', $customer), storeCustomerForm(['tax_number' => null, 'trade_license' => null, 'email' => "blank-{$index}@example.com", 'phone' => "3160000010{$index}"]))
            ->assertSessionHasNoErrors();
    }

    expect($first->fresh()->tax_number)->toBeNull()->and($second->fresh()->tax_number)->toBeNull();
});

test('the customer search finds a customer by tax number', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    $customer = createStoreUser();
    $customer->forceFill(['tax_number' => 'NL777888999B01'])->save();
    $noise = createStoreUser();
    $noise->forceFill(['tax_number' => 'BE0123456789'])->save();

    $this->get(route('customers.index', ['search' => '777888']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('customers.data', 1)
        ->where('customers.data.0.id', $customer->id));
});

/**
 * @return array{a: StoreUser, b: StoreUser, rep: StoreUser}
 */
function seedProfitRangeScenario(): array
{
    $a = createStoreUser('customer', 'range-a@example.com');
    $b = createStoreUser('customer', 'range-b@example.com');
    $rep = createStoreUser('customer', 'range-rep@example.com');
    $a->forceFill(['profits' => 999, 'name' => 'Alpha'])->save();
    $b->forceFill(['profits' => 1, 'name' => 'Bravo'])->save();
    $rep->forceFill(['is_rep' => true, 'profits' => 20, 'name' => 'Rep Zed'])->save();
    foreach ([
        // At the lower bound: counted.
        [$a, 10, '2026-11-01 12:00:00', []],
        // Inside the last minute of the upper bound, and cancelled: still counted.
        [$a, 5, '2026-11-02 12:00:30', ['delivery_status' => 'cancelled']],
        // A minute past the upper bound, and long before the lower bound: not counted.
        [$a, 1000, '2026-11-02 12:01:00', []],
        [$a, 100, '2026-10-01 09:00:00', []],
        // Placed by the representative for A: counts for both accounts.
        [$rep, 7, '2026-11-01 13:00:00', ['by_rep' => 1, 'for_customer' => $a->id]],
        // A's own order whose for_customer names B without by_rep: B gets nothing.
        [$a, 50, '2026-11-01 14:00:00', ['for_customer' => $b->id]],
        [$b, 3, '2026-11-01 12:30:00', []],
    ] as [$owner, $profits, $createdAt, $extra]) {
        createStoreOrder($owner, ['profits' => $profits, 'created_at' => $createdAt, ...$extra]);
    }

    return ['a' => $a, 'b' => $b, 'rep' => $rep];
}

/** @return array<int, float|int> */
function listedProfits(TestResponse $response): array
{
    return collect($response->assertOk()->inertiaProps()['customers']['data'])->pluck('profits', 'id')->all();
}

test('the customers list shows every agreed property, never a secret', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers', 'edit_customer'));
    $customer = createStoreUser('customer', 'columns@example.com');
    $customer->forceFill(['company_name' => 'Acme', 'country' => 'Netherlands', 'tax_number' => 'VAT1', 'bank_account_number' => 'NL99', 'trade_license' => 'license.pdf', 'company_address' => 'Company Road', 'shipping_address' => 'Shipping Road', 'member_serial' => '42', 'AccSysID' => 7, 'admin_notes' => 'Call later', 'registration_completed' => 1, 'from_api' => 1, 'profits' => 12.5, 'balance' => 3.5, 'address' => 'Old street', 'state' => 'NH', 'city' => 'Haarlem', 'postal_code' => '2000', 'last_order_at' => '2026-10-30', 'admin_verified_at' => '2026-10-01', 'remember_token' => 'remember-secret', 'verification_code' => 'code-secret', 'device_token' => 'device-secret', 'device_key' => 'key-secret'])->save();
    $record = $this->get(route('customers.index'))->assertOk()->inertiaProps()['customers']['data'][0];
    expect($record)->toMatchArray([
        'id' => $customer->id, 'company_name' => 'Acme', 'user_type' => 'customer', 'country' => 'Netherlands', 'tax_number' => 'VAT1', 'bank_account_number' => 'NL99', 'trade_license' => 'license.pdf',
        'company_address' => 'Company Road', 'shipping_address' => 'Shipping Road', 'member_serial' => '42', 'AccSysID' => 7, 'admin_notes' => 'Call later', 'registration_completed' => true, 'from_api' => true,
        'profits' => 12.5, 'address' => 'Old street', 'state' => 'NH', 'city' => 'Haarlem', 'postal_code' => '2000', 'last_order_at' => '2026-10-30', 'admin_verified_at' => '2026-10-01',
    ])->and($record)->toHaveKeys(['email_verified_at', 'created_at', 'updated_at', 'balance', 'rep_discount_percentage', 'rep_id', 'rep_serial']);
    expect(json_encode($record))->not->toContain('secret')->and(array_keys($record))->not->toContain('password')->not->toContain('remember_token')->not->toContain('verification_code')->not->toContain('device_token')->not->toContain('device_key');
});

test('tax number, bank account, trade licence and balance need the edit permission', function () {
    $customer = createStoreUser('customer', 'restricted@example.com');
    $customer->forceFill(['tax_number' => 'VAT1', 'bank_account_number' => 'NL99', 'trade_license' => 'license.pdf', 'balance' => 3.5, 'country' => 'Netherlands'])->save();
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    $record = $this->get(route('customers.index'))->assertOk()->inertiaProps()['customers']['data'][0];
    expect($record)->toHaveKey('country')->not->toHaveKeys(['tax_number', 'bank_account_number', 'trade_license', 'balance']);
    expect(json_encode($record))->not->toContain('VAT1')->not->toContain('NL99')->not->toContain('license.pdf');
});

test('the profits column is the stored total without a range', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    ['a' => $a, 'b' => $b] = seedProfitRangeScenario();
    $profits = listedProfits($this->get(route('customers.index')));
    expect($profits[$a->id])->toEqual(999.0)->and($profits[$b->id])->toEqual(1.0);
    $this->get(route('customers.index', ['profit_from' => '', 'profit_to' => '']))->assertInertia(fn (Assert $page) => $page->missing('filters.profit_from')->missing('filters.profit_to'));
});

test('a profit range sums the orders created in it for the owner and the customer a representative ordered for', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    ['a' => $a, 'b' => $b, 'rep' => $rep] = seedProfitRangeScenario();
    $response = $this->get(route('customers.index', ['profit_from' => '2026-11-01T12:00', 'profit_to' => '2026-11-02T12:00']));
    $profits = listedProfits($response);
    // A: 10 + 5 (cancelled, last minute) + 7 (placed by the rep for A) + 50; never the 1000 or 100 outside the range.
    expect($profits[$a->id])->toEqual(72.0)->and($profits[$b->id])->toEqual(3.0)->and($profits)->not->toHaveKey($rep->id);
    $response->assertInertia(fn (Assert $page) => $page->where('filters.profit_from', '2026-11-01T12:00')->where('filters.profit_to', '2026-11-02T12:00')->where('customers.meta.total', 2));

    $empty = createStoreUser('customer', 'range-empty@example.com');
    $empty->forceFill(['profits' => 400])->save();
    expect(listedProfits($this->get(route('customers.index', ['profit_from' => '2026-11-01T12:00', 'profit_to' => '2026-11-02T12:00'])))[$empty->id])->toEqual(0.0);
});

test('a profit range can be open at either end', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    ['a' => $a] = seedProfitRangeScenario();
    expect(listedProfits($this->get(route('customers.index', ['profit_from' => '2026-11-02T00:00'])))[$a->id])->toEqual(1005.0);
    expect(listedProfits($this->get(route('customers.index', ['profit_to' => '2026-10-15T00:00'])))[$a->id])->toEqual(100.0);
});

test('the representatives and archived lists sum the range the same way', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers', 'delete_customer'));
    ['a' => $a, 'rep' => $rep] = seedProfitRangeScenario();
    $range = ['profit_from' => '2026-11-01T12:00', 'profit_to' => '2026-11-02T12:00'];
    expect(listedProfits($this->get(route('representatives.index', $range))))->toEqual([$rep->id => 7.0]);
    expect(listedProfits($this->get(route('representatives.index'))))->toEqual([$rep->id => 20.0]);
    $a->forceFill(['is_delete' => 1])->save();
    expect(listedProfits($this->get(route('customers.archive', $range))))->toEqual([$a->id => 72.0]);
});

test('the profit range stays on every page and with search and archive', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers', 'delete_customer'));
    ['a' => $a] = seedProfitRangeScenario();
    foreach (range(1, 24) as $number) {
        createStoreUser();
    }
    $range = ['profit_from' => '2026-11-01T12:00', 'profit_to' => '2026-11-02T12:00'];
    $first = $this->get(route('customers.index', $range))->assertOk()->inertiaProps()['customers'];
    expect($first['data'])->toHaveCount(20)->and($first['meta']['total'])->toBe(26);
    expect(collect($first['meta']['links'])->pluck('url')->filter()->every(fn (string $url) => str_contains($url, 'profit_from=2026-11-01T12%3A00') && str_contains($url, 'profit_to=2026-11-02T12%3A00')))->toBeTrue();
    $this->get(route('customers.index', [...$range, 'page' => 2]))->assertInertia(fn (Assert $page) => $page->has('customers.data', 6)->where('filters.profit_from', '2026-11-01T12:00'));
    $searched = $this->get(route('customers.index', [...$range, 'search' => 'Alpha']))->assertOk()->inertiaProps();
    expect(collect($searched['customers']['data'])->pluck('profits', 'id')->all())->toEqual([$a->id => 72.0])->and($searched['filters']['search'])->toBe('Alpha');
    $a->forceFill(['is_delete' => 1])->save();
    expect(listedProfits($this->get(route('customers.index', [...$range, 'archived' => 1, 'search' => 'Alpha']))))->toEqual([$a->id => 72.0]);
});

test('the profit range is one grouped query however many customers are listed', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('customers.index', ['profit_from' => '2026-11-01T12:00', 'profit_to' => '2026-11-02T12:00']))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $queries();
    foreach (range(1, 2) as $number) {
        createStoreOrder(createStoreUser(), ['profits' => 4, 'created_at' => '2026-11-01 13:00:00']);
    }
    $few = $queries();
    foreach (range(1, 8) as $number) {
        createStoreOrder(createStoreUser(), ['profits' => 4, 'created_at' => '2026-11-01 13:00:00']);
    }
    expect($queries())->toBe($few);
});

test('an invalid profit range is refused and never applied', function (array $query, string $field) {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    $this->get(route('customers.index', $query))->assertSessionHasErrors($field);
})->with([
    'from after to' => [['profit_from' => '2026-11-03T10:00', 'profit_to' => '2026-11-03T09:59'], 'profit_to'],
    'date only' => [['profit_from' => '2026-11-03'], 'profit_from'],
    'space instead of T' => [['profit_to' => '2026-11-03 10:00'], 'profit_to'],
    'not a date' => [['profit_from' => 'yesterday'], 'profit_from'],
    'impossible time' => [['profit_to' => '2026-11-03T25:00'], 'profit_to'],
]);

test('equal profit range bounds are accepted', function () {
    actingAsStoreUser(createStoreStaff('view_all_customers'));
    $this->get(route('customers.index', ['profit_from' => '2026-11-03T10:00', 'profit_to' => '2026-11-03T10:00']))->assertOk()->assertSessionHasNoErrors();
});

foreach (['customers.index', 'representatives.index'] as $route) {
    test($route.' refuses unauthorized callers who send a profit range', function (string $caller) use ($route) {
        seedProfitRangeScenario();
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get(route($route, ['profit_from' => '2026-11-01T12:00', 'profit_to' => '2026-11-02T12:00'])), $caller);
    })->with('refused store admin callers');
}
