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
        ->where('id', $customer->id)->where('name', 'Amy')->where('email', 'amy@example.com')->where('phone', '31611111111')->where('company_name', 'Amy Company')->where('is_rep', false)->where('archived', false)->where('banned', false)->where('verified', true)->where('important', true)->where('activity_status', 'new_user')->where('rep_id', null)->where('rep_serial', null)));
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
    $customer = createStoreUser();
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
    $this->get(route('customers.page-views', $customer))->assertInertia(fn (Assert $page) => $page->has('views.data', 20)->where('views.meta.total', 23)->where('views.data.0.view_count', 2)->where('views.data.0', ['id' => $ids[0], 'kind' => 'products', 'name' => 'Viewed product 1', 'slug' => 'viewed-product-1', 'view_count' => 2, 'last_viewed_at' => '2026-10-08 13:00:00']));
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
