<?php

use App\Models\StoreUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Store endpoints that handed private data to anyone: the rep customer
 * search returned whole user rows, the shop and business-setting APIs
 * returned payout, bank and credential fields, the rep list carried each
 * rep's password hash, three trip routes and two customer routes took no
 * sign-in, and unpublished products could be read by id.
 */
const SENSITIVE_USER_KEYS = [
    'password', 'remember_token', 'verification_code', 'device_token', 'device_key',
    'bank_account_number', 'provider_id', 'tax_number', 'admin_notes', 'email', 'phone',
];

/**
 * A store user carrying a value in every column the leak tests look for.
 */
function createUserWithSecrets(string $userType = 'customer', array $attributes = []): StoreUser
{
    $user = createStoreUser($userType);
    $user->forceFill([
        'verification_code' => '123456',
        'device_token' => 'device-token-secret',
        'device_key' => 'device-key-secret',
        'bank_account_number' => 'NL00BANK0123456789',
        'provider_id' => 'provider-secret',
        'tax_number' => 'TAX-SECRET',
        'admin_notes' => 'admin-only note',
        'company_name' => 'Company '.$user->id,
        ...$attributes,
    ])->save();

    return $user->fresh();
}

function createRep(): StoreUser
{
    return createUserWithSecrets('customer', ['is_rep' => true]);
}

// search_users

test('the customer search turns away a guest', function () {
    createUserWithSecrets();

    $this->getJson(route('search.users'))->assertUnauthorized();
});

test('the customer search turns away a customer who is not a representative, a seller and a driver', function (string $userType) {
    createUserWithSecrets();
    actingAsStoreUser(createStoreUser($userType));

    $this->getJson(route('search.users'))->assertForbidden();
})->with(['customer', 'seller', 'driver']);

test('the customer search turns away a banned representative', function () {
    $rep = createRep();
    $rep->forceFill(['banned' => 1])->save();
    actingAsStoreUser($rep);

    $this->getJson(route('search.users'))->assertForbidden();
});

test('a representative finds customers by name, phone or company and gets only id, name and company', function () {
    $match = createUserWithSecrets('customer', ['name' => 'Zebra Trading', 'company_name' => 'Zebra BV']);
    $byCompany = createUserWithSecrets('customer', ['name' => 'Someone Else', 'company_name' => 'Zebra Foods']);
    $byPhone = createUserWithSecrets('customer', ['name' => 'Phone Match', 'phone' => '1234Zebra99']);
    $noise = createUserWithSecrets('customer', ['name' => 'Unrelated', 'company_name' => 'Other BV']);
    $otherRep = createUserWithSecrets('customer', ['name' => 'Zebra Rep', 'is_rep' => true]);
    $admin = createUserWithSecrets('admin', ['name' => 'Zebra Admin']);
    actingAsStoreUser(createRep());

    $users = $this->getJson(route('search.users', ['query' => 'Zebra']))->assertOk()->json('users');

    expect(collect($users)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$match->id, $byCompany->id, $byPhone->id])->sort()->values()->all())
        ->and(collect($users)->pluck('id')->all())->not->toContain($noise->id, $otherRep->id, $admin->id);

    foreach ($users as $user) {
        expect(array_keys($user))->toEqualCanonicalizing(['id', 'name', 'company_name']);
    }
});

test('the customer search without a query lists customers only, never the caller, other representatives or admins', function () {
    $customer = createUserWithSecrets();
    $otherRep = createRep();
    $admin = createUserWithSecrets('admin');
    $rep = createRep();
    actingAsStoreUser($rep);

    $ids = collect($this->getJson(route('search.users'))->assertOk()->json('users'))->pluck('id');

    expect($ids->all())->toBe([$customer->id])
        ->and($ids)->not->toContain($rep->id, $otherRep->id, $admin->id);
});

test('the customer search returns at most ten users and exposes no sensitive key', function () {
    foreach (range(1, 12) as $number) {
        createUserWithSecrets('customer', ['name' => "Bulk {$number}"]);
    }
    actingAsStoreUser(createRep());

    $response = $this->getJson(route('search.users', ['query' => 'Bulk']))->assertOk();

    expect($response->json('users'))->toHaveCount(10);

    foreach (SENSITIVE_USER_KEYS as $key) {
        expect($response->json('users.0'))->not->toHaveKey($key);
    }
});

test('an admin may use the customer search', function () {
    $customer = createUserWithSecrets();
    actingAsStoreUser(createStoreAdmin());

    $this->getJson(route('search.users'))
        ->assertOk()
        ->assertJsonPath('users.0.id', $customer->id);
});

// StoreUser serialisation and the rep list

test('a store user serialises without its secrets', function () {
    $array = createUserWithSecrets()->toArray();

    foreach (['password', 'remember_token', 'verification_code', 'device_token', 'device_key', 'bank_account_number', 'provider_id', 'tax_number'] as $key) {
        expect($array)->not->toHaveKey($key);
    }
});

test('the warehouse rep list carries no password and skips customers who are not reps', function () {
    config(['app.wms_token' => 'wms-secret']);
    $rep = createRep();
    $customer = createUserWithSecrets();

    $response = $this->withHeaders(['private-token' => 'wms-secret'])->getJson('/temp/api/v2/warehouse/reps')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->all())->toBe([$rep->id])->and($ids)->not->toContain($customer->id);
    expect($response->json('data.0'))->not->toHaveKey('password');
});

// shop details

function createPublicShop(array $attributes = []): int
{
    $seller = createStoreUser('seller');
    Cache::forget('verified_sellers_id');

    return DB::table('shops')->insertGetId([
        'user_id' => $seller->id,
        'name' => 'Public Shop',
        'verification_status' => 1,
        'admin_to_pay' => 987.65,
        'phone' => '0612345678',
        'bank_name' => 'Secret Bank',
        'bank_acc_name' => 'Secret Holder',
        'bank_acc_no' => 'NL99SECRET',
        'bank_routing_no' => 4242,
        'bank_payment_status' => 1,
        'cash_on_delivery_status' => 1,
        ...$attributes,
    ]);
}

test('a visitor reads a verified shop without payout, bank, sales or contact details', function () {
    $shopId = createPublicShop();

    $response = $this->getJson("/temp/api/v2/shops/details/{$shopId}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Public Shop');

    foreach (['admin_to_pay', 'bank_name', 'bank_acc_name', 'bank_acc_no', 'bank_routing_no', 'bank_payment_status', 'cash_on_delivery_status', 'sales', 'orders', 'phone', 'email', 'seller_package', 'package_invalid_at', 'product_upload_limit'] as $key) {
        expect($response->json('data'))->not->toHaveKey($key);
    }
});

test('a visitor gets a 404 for a shop that is not verified or does not exist', function () {
    $unverified = createPublicShop(['verification_status' => 0]);
    $verified = createPublicShop();

    $this->getJson("/temp/api/v2/shops/details/{$unverified}")->assertNotFound();
    $this->getJson('/temp/api/v2/shops/details/99999999')->assertNotFound();
    $this->getJson("/temp/api/v2/shops/details/{$verified}")->assertOk();
});

test('the shop counts only published products', function () {
    $shopId = createPublicShop();
    $sellerId = DB::table('shops')->where('id', $shopId)->value('user_id');
    createStoreProduct(['user_id' => $sellerId, 'added_by' => 'seller']);
    createStoreProduct(['user_id' => $sellerId, 'added_by' => 'seller', 'published' => false]);

    $this->getJson("/temp/api/v2/shops/details/{$shopId}")->assertJsonPath('data.products', 1);
});

// business settings

test('the public business settings list only allow-listed keys', function () {
    DB::table('business_settings')->insert([
        ['type' => 'site_name', 'value' => 'Al Baraka'],
        ['type' => 'smtp_password', 'value' => 'mail-secret'],
        ['type' => 'stripe_secret', 'value' => 'stripe-secret'],
        ['type' => 'google_map_api_key', 'value' => 'map-secret'],
    ]);

    $response = $this->getJson('/temp/api/v2/business-settings')->assertOk();

    $types = collect($response->json('data'))->pluck('type');
    expect($types->all())->toBe(['site_name'])
        ->and($response->getContent())->not->toContain('mail-secret', 'stripe-secret', 'map-secret');
});

// trip routes

test('the trip lookups turn away a guest, a customer and a seller', function (string $method, string $uri, ?string $userType) {
    $target = createUserWithSecrets();
    $uri = str_replace('{id}', (string) $target->id, $uri);

    $request = $userType === null ? $this : $this->withToken(storeApiToken(createStoreUser($userType)));

    $response = $request->json($method, $uri);

    expect($response->status())->toBe($userType === null ? 401 : 403);
    expect($response->getContent())->not->toContain('latitude', 'Customer Tester');
})->with([
    'coordinates, guest' => ['GET', '/temp/api/get-user-coordinates/{id}', null],
    'coordinates, customer' => ['GET', '/temp/api/get-user-coordinates/{id}', 'customer'],
    'coordinates, seller' => ['GET', '/temp/api/get-user-coordinates/{id}', 'seller'],
    'vehicles, guest' => ['GET', '/temp/api/v2/trips/get-available-vehicles', null],
    'vehicles, customer' => ['GET', '/temp/api/v2/trips/get-available-vehicles', 'customer'],
    'vehicles, seller' => ['GET', '/temp/api/v2/trips/get-available-vehicles', 'seller'],
]);

test('a driver reads the trip lookups', function () {
    $driver = createStoreUser('driver');
    $customer = createStoreUser('customer');

    $this->withToken(storeApiToken($driver))->getJson('/temp/api/v2/trips/get-available-vehicles?date='.now()->toDateString())->assertOk();
    $this->withToken(storeApiToken($driver))->getJson("/temp/api/get-user-coordinates/{$customer->id}")->assertOk();
});

test('the admin trip forms look up vehicles and coordinates with the admin session, and nobody else may', function () {
    $customer = createStoreUser('customer');
    $uris = [route('trips.available-vehicles', ['date' => now()->toDateString()]), route('trips.user-coordinates', $customer->id)];

    foreach ($uris as $uri) {
        $this->getJson($uri)->assertUnauthorized();
    }

    actingAsStoreUser(createStoreUser('customer'));
    foreach ($uris as $uri) {
        $this->getJson($uri)->assertNotFound();
    }

    actingAsStoreUser(createStoreAdmin());
    foreach ($uris as $uri) {
        $this->getJson($uri)->assertOk();
    }
});

// customer routes

test('the customer profile and verification routes turn away a guest', function (string $uri) {
    $customer = createUserWithSecrets();

    $this->getJson(str_replace('{id}', (string) $customer->id, $uri))->assertUnauthorized();
})->with([
    'customers.show' => '/temp/api/v2/customers/{id}',
    'check_user_verified' => '/temp/api/v2/check_user_verified/{id}',
]);

test('a signed-in customer reads their own profile and no one else', function () {
    seedStoreCurrency();
    $customer = createUserWithSecrets();
    $other = createUserWithSecrets();

    $response = $this->withToken(storeApiToken($customer))->getJson("/temp/api/v2/customers/{$customer->id}")->assertOk();

    expect($response->getContent())->toContain((string) $customer->email)
        ->and($response->getContent())->not->toContain((string) $other->email);
});

test('a signed-in user can ask whether an account is verified', function () {
    $customer = createStoreUser('customer');

    $this->withToken(storeApiToken($customer))->getJson("/temp/api/v2/check_user_verified/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('verified_by_admin', false);
});

// unpublished products

test('the product list leaves out unpublished and unapproved products and is paginated', function () {
    seedStoreCurrency();
    $unpublished = createStoreProduct(['published' => false]);
    $unapproved = createStoreProduct(['approved' => 0]);
    foreach (range(1, 11) as $ignored) {
        createStoreProduct();
    }

    $response = $this->getJson('/temp/api/v2/products')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toHaveCount(10)
        ->and($ids)->not->toContain($unpublished->id, $unapproved->id);
    expect(collect($this->getJson('/temp/api/v2/products?page=2')->json('data'))->pluck('id'))
        ->toHaveCount(1)
        ->not->toContain($unpublished->id, $unapproved->id);
});

test('the new-products list leaves out unpublished products', function () {
    seedStoreCurrency();
    $new = createStoreProduct(['isNewProduct' => true]);
    $offer = createStoreProduct(['isOfferProduct' => true]);
    $unpublished = createStoreProduct(['isNewProduct' => true, 'published' => false]);
    $plain = createStoreProduct();

    $ids = collect($this->getJson('/temp/api/v2/products/new_products')->assertOk()->json('data'))->pluck('id');

    expect($ids->all())->toContain($new->id, $offer->id)
        ->and($ids->all())->not->toContain($unpublished->id, $plain->id);
});

test('a product that is unpublished or unapproved is a 404 by id and a published one is not', function () {
    seedStoreCurrency();
    $published = createStoreProduct(['added_by' => 'admin']);
    $unpublished = createStoreProduct(['published' => false]);
    $unapproved = createStoreProduct(['approved' => 0]);

    $this->getJson("/temp/api/v2/products/{$unpublished->id}")->assertNotFound();
    $this->getJson("/temp/api/v2/products/{$unapproved->id}")->assertNotFound();
    $this->getJson("/temp/api/v2/products/{$published->id}")->assertSuccessful();
});

test('filter_products leaves out unpublished products when vendors are active', function () {
    seedStoreCurrency();
    DB::table('business_settings')->insert(['type' => 'vendor_system_activation', 'value' => 1]);
    $published = createStoreProduct(['todays_deal' => 1, 'added_by' => 'admin']);
    $unpublished = createStoreProduct(['todays_deal' => 1, 'added_by' => 'admin', 'published' => false]);

    $ids = collect($this->getJson('/temp/api/v2/products/todays-deal')->assertOk()->json('data'))->pluck('id');

    expect($ids->all())->toBe([$published->id])->and($ids)->not->toContain($unpublished->id);
});

test('the product page of an unpublished product is a 404', function () {
    seedStoreCurrency();
    $published = createStoreProduct();
    $unpublished = createStoreProduct(['published' => false]);

    $this->get(route('product', $unpublished->slug))->assertNotFound();
    $this->get(route('product', $published->slug))->assertOk();
});
