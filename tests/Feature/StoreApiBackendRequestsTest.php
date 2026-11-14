<?php

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\StoreUser;
use App\Models\Upload;
use App\Models\Wishlist;
use App\Services\ThumbnailService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * The store API changes asked for in BR-20261105-A, BR-20261103-A/B/C, BR-20261102-B/D and
 * BR-20261101-A: who owns a rep's order, the delivery date rules, the order
 * idempotency key, the account object, state changes without GET, the cart
 * note, the paged customer list and the resized thumbnails.
 */
function orderingStore(): void
{
    seedStoreCurrency();
    storeSetting('no_of_decimals', 2);
    storeSetting('cash_payment', 1);
    createStoreUser('admin');
}

/** A representative with a cart line for a customer who has an address. */
function repWithCartFor(StoreUser $customer): StoreUser
{
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => true])->save();
    $address = addStoreAddress($customer->id);
    $collection = ProductCollection::create(['name' => 'Margin', 'serial' => 'C1', 'amount' => 10]);
    $product = createStoreProduct(['cash_on_delivery' => 1, 'collection_id' => $collection->id]);
    Cart::create(['user_id' => $rep->id, 'owner_id' => 0, 'product_id' => $product->id, 'quantity' => 3, 'variation' => '', 'price' => 2.5, 'by_rep' => 1, 'for_customer' => $customer->id, 'address_id' => $address]);

    return $rep;
}

function customerCartWithOneLine(StoreUser $customer): Cart
{
    $product = createStoreProduct(['cash_on_delivery' => 1]);

    return Cart::create(['user_id' => $customer->id, 'owner_id' => 0, 'product_id' => $product->id, 'quantity' => 1, 'variation' => '', 'price' => 2.5, 'address_id' => addStoreAddress($customer->id)]);
}

// ---- BR-20261103-B.1 / BR-20261102-B.1: the account object -------------------------------------

test('GET auth/user returns the account object sign-in returns, and issues no token', function () {
    $customer = createStoreUser('customer', 'me@example.com');
    $customer->forceFill(['verification_code' => '123456', 'provider_id' => 'google-1', 'tax_number' => 'NL123', 'bank_account_number' => 'NL00BANK', 'company_name' => 'Acme'])->save();
    createStoreUser('customer', 'noise@example.com');
    $tokens = DB::table('wms_personal_access_tokens')->count();

    $response = $this->withToken(storeApiToken($customer))->getJson('/api/v1/store/auth/user')->assertOk();

    expect($response->json())->toEqual([
        'id' => $customer->id,
        'type' => 'customer',
        'name' => $customer->name,
        'otajer_id' => null,
        'company_name' => 'Acme',
        'email' => 'me@example.com',
        'avatar' => null,
        'avatar_original' => static_asset('assets/img/placeholder.jpg'),
        'phone' => $customer->phone,
        'is_rep' => false,
        'rep_has_package' => false,
        'rep_discount_percentage' => 0,
        'company_address' => null,
        'shipping_address' => null,
        'tax_number' => 'NL123',
        'country' => $customer->country,
        'trade_license' => null,
        'bank_account_number' => 'NL00BANK',
        'email_verified' => true,
        'admin_verified' => 1,
    ])
        ->and($response->getContent())->not->toContain('verification_code')->not->toContain('provider_id')->not->toContain('refresh_token')
        ->and(DB::table('wms_personal_access_tokens')->count())->toBe($tokens + 1); // the one storeApiToken() made
});

test('POST auth/info answers with the token it was given and issues no new one', function () {
    $customer = createStoreUser('customer');
    $token = storeApiToken($customer);
    $tokens = DB::table('wms_personal_access_tokens')->count();

    $this->postJson('/api/v1/store/auth/info', ['access_token' => $token])
        ->assertOk()->assertJsonPath('access_token', $token)->assertJsonPath('user.id', $customer->id);

    expect(DB::table('wms_personal_access_tokens')->count())->toBe($tokens);
});

// ---- BR-20261102-B.3 / BR-20261103-B.7: validation is 422 ---------------------------------------

test('sign-up refuses invalid input with 422 and creates no account', function () {
    $this->postJson('/api/v1/store/auth/signup', ['register_by' => 'email'])
        ->assertUnprocessable()
        ->assertJsonPath('result', false)
        ->assertJsonStructure(['message', 'errors' => ['name', 'password', 'email_or_phone']]);

    expect(DB::table('users')->count())->toBe(0);
});

// ---- BR-20261103-A.2: the delivery date ---------------------------------------------------------

test('order/store refuses a blank, missing, unreadable or early delivery date and places nothing', function (?string $date, bool $sent, string $message) {
    orderingStore();
    $customer = createStoreUser('customer');
    customerCartWithOneLine($customer);
    $date = $date === 'tomorrow' ? CarbonImmutable::now(config('store.delivery.timezone'))->addDay()->toDateString() : $date;

    $response = $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/order/store', ['payment_type' => 'cash_on_delivery', ...($sent ? ['delivery_date' => $date] : [])])
        ->assertUnprocessable()
        ->assertJsonPath('result', false)
        ->assertJsonPath('combined_order_id', 0);

    // The message up to its placeholder, if it has one: the earliest date moves with the clock.
    $prefix = Str::before(__($message, ['date' => '@@']), '@@');
    expect($response->json('errors.delivery_date.0'))->toStartWith($prefix)
        ->and($response->json('message'))->toBe($response->json('errors.delivery_date.0'))
        ->and(Order::count())->toBe(0)
        ->and(Cart::where('user_id', $customer->id)->count())->toBe(1);
})->with([
    'blank' => ['', true, 'api.order.delivery_date_required'],
    'missing' => [null, false, 'api.order.delivery_date_required'],
    'not a date' => ['next week', true, 'api.order.delivery_date_invalid'],
    'a day that does not exist' => ['2030-02-30', true, 'api.order.delivery_date_invalid'],
    'in the past' => ['2020-01-06', true, 'api.order.delivery_date_too_soon'],
    'tomorrow' => ['tomorrow', true, 'api.order.delivery_date_too_soon'],
]);

test('order/store refuses a Saturday and a Sunday', function (string $day) {
    orderingStore();
    $customer = createStoreUser('customer');
    customerCartWithOneLine($customer);
    $weekend = CarbonImmutable::now(config('store.delivery.timezone'))->addWeeks(2)->next($day)->toDateString();

    $response = $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/order/store', ['payment_type' => 'cash_on_delivery', 'delivery_date' => $weekend])
        ->assertUnprocessable();

    expect($response->json('errors.delivery_date.0'))->toContain('Monday')->toContain('Friday')
        ->and(Order::count())->toBe(0);
})->with(['Saturday', 'Sunday']);

test('order/store stores a valid delivery date as YYYY-MM-DD, however the client wrote it', function (string $format) {
    orderingStore();
    $customer = createStoreUser('customer');
    customerCartWithOneLine($customer);
    $date = validDeliveryDate();

    $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/order/store', ['payment_type' => 'cash_on_delivery', 'delivery_date' => CarbonImmutable::parse($date)->format($format)])
        ->assertOk()->assertJsonPath('result', true);

    expect(Order::sole()->delivery_date)->toBe($date);
})->with(['Y-m-d', 'd-m-Y']);

// ---- BR-20261103-A.1: who owns a rep's order ----------------------------------------------------

test('a rep\'s order names the customer on the shipping address and in both histories', function () {
    orderingStore();
    $customer = createStoreUser('customer', 'customer@example.com');
    $customer->forceFill(['name' => 'Customer Shop'])->save();
    $rep = repWithCartFor($customer);
    $rep->forceFill(['name' => 'The Rep'])->save();
    $stranger = createStoreUser('customer');
    $strangerOrder = createStoreOrder($stranger);

    $combined = $this->withToken(storeApiToken($rep))->postJson('/api/v1/store/order/store', ['payment_type' => 'cash_on_delivery', 'delivery_date' => validDeliveryDate()])
        ->assertOk()->json('combined_order_id');
    $order = Order::latest('id')->first();

    $details = $this->withToken(storeApiToken($rep))->getJson('/api/v1/store/purchase-history-details/'.$order->id)->assertOk()->json('data.0');
    expect($details['shipping_address']['name'])->toBe('Customer Shop')
        ->and($details['shipping_address']['email'])->toBe('customer@example.com')
        ->and($details['customer_id'])->toBe($customer->id)
        ->and($details['customer_name'])->toBe('Customer Shop')
        ->and($details['placed_by_id'])->toBe($rep->id)
        ->and($details['placed_by_name'])->toBe('The Rep')
        ->and($details['placed_by_rep'])->toBeTrue()
        ->and($details['user_id'])->toBe($rep->id);

    app('auth')->forgetGuards();
    $repList = $this->withToken(storeApiToken($rep))->getJson('/api/v1/store/purchase-history')->assertOk()->json('data');
    expect($repList)->toHaveCount(1)
        ->and($repList[0])->toMatchArray(['id' => $order->id, 'customer_id' => $customer->id, 'name' => 'Customer Shop', 'placed_by_id' => $rep->id, 'placed_by_name' => 'The Rep', 'placed_by_rep' => true, 'user_id' => $rep->id]);

    app('auth')->forgetGuards();
    $customerList = $this->withToken(storeApiToken($customer))->getJson('/api/v1/store/purchase-history')->assertOk()->json('data');
    expect(collect($customerList)->pluck('id')->all())->toBe([$order->id])
        ->and($customerList[0]['placed_by_rep'])->toBeTrue()
        ->and($combined)->toBeInt();

    app('auth')->forgetGuards();
    $this->withToken(storeApiToken($customer))->getJson('/api/v1/store/purchase-history-details/'.$strangerOrder->id)->assertForbidden();
});

test('an ordinary order is its own customer and was not placed by a rep', function () {
    orderingStore();
    $customer = createStoreUser('customer');
    $order = createStoreOrder($customer);

    $row = $this->withToken(storeApiToken($customer))->getJson('/api/v1/store/purchase-history')->assertOk()->json('data.0');

    expect($row)->toMatchArray(['id' => $order->id, 'user_id' => $customer->id, 'customer_id' => $customer->id, 'placed_by_id' => $customer->id, 'placed_by_rep' => false, 'name' => $customer->name]);
});

test('the purchase history payment filter applies to the orders placed for the account too', function () {
    orderingStore();
    $customer = createStoreUser('customer');
    $rep = createStoreUser('customer');
    createStoreOrder($customer, ['payment_status' => 'paid']);
    $unpaidForCustomer = createStoreOrder($rep, ['for_customer' => $customer->id, 'by_rep' => 1, 'payment_status' => 'unpaid']);
    createStoreOrder($rep, ['for_customer' => $customer->id, 'by_rep' => 1, 'payment_status' => 'paid']);
    createStoreOrder(createStoreUser('customer'), ['payment_status' => 'unpaid']);

    $ids = $this->withToken(storeApiToken($customer))->getJson('/api/v1/store/purchase-history?payment_status=unpaid')->assertOk()->json('data.*.id');

    expect($ids)->toBe([$unpaidForCustomer->id]);
});

// ---- BR-20261103-B.2: order idempotency ---------------------------------------------------------

test('order/store with the same Idempotency-Key places one order and replays the first answer', function () {
    orderingStore();
    $customer = createStoreUser('customer');
    customerCartWithOneLine($customer);
    $token = storeApiToken($customer);
    $body = ['payment_type' => 'cash_on_delivery', 'delivery_date' => validDeliveryDate()];

    $first = $this->withToken($token)->withHeader('Idempotency-Key', 'phone-attempt-1')->postJson('/api/v1/store/order/store', $body)->assertOk()->assertJsonPath('result', true);
    app('auth')->forgetGuards();
    // The cart was emptied by the first call, as it would be after a timeout the phone never saw.
    $second = $this->withToken($token)->withHeader('Idempotency-Key', 'phone-attempt-1')->postJson('/api/v1/store/order/store', $body)->assertOk()->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json())->toEqual($first->json())->and(Order::count())->toBe(1);

    app('auth')->forgetGuards();
    $other = $this->withToken($token)->withHeader('Idempotency-Key', 'phone-attempt-2')->postJson('/api/v1/store/order/store', $body)->assertOk();
    expect($other->json('result'))->toBeFalse()->and(Order::count())->toBe(1);
});

test('an Idempotency-Key is not shared between accounts, and a refusal can be retried with it', function () {
    orderingStore();
    $first = createStoreUser('customer');
    $second = createStoreUser('customer');
    customerCartWithOneLine($first);
    customerCartWithOneLine($second);
    $body = ['payment_type' => 'cash_on_delivery', 'delivery_date' => validDeliveryDate()];

    $this->withToken(storeApiToken($first))->withHeader('Idempotency-Key', 'same-key')->postJson('/api/v1/store/order/store', $body)->assertOk()->assertJsonPath('result', true);
    app('auth')->forgetGuards();
    $this->withToken(storeApiToken($second))->withHeader('Idempotency-Key', 'same-key')->postJson('/api/v1/store/order/store', $body)
        ->assertOk()->assertJsonPath('result', true)->assertHeaderMissing('Idempotent-Replayed');
    expect(Order::count())->toBe(2);

    $third = createStoreUser('customer');
    customerCartWithOneLine($third);
    app('auth')->forgetGuards();
    $this->withToken(storeApiToken($third))->withHeader('Idempotency-Key', 'retry')->postJson('/api/v1/store/order/store', [...$body, 'delivery_date' => ''])->assertUnprocessable();
    app('auth')->forgetGuards();
    $this->withToken(storeApiToken($third))->withHeader('Idempotency-Key', 'retry')->postJson('/api/v1/store/order/store', $body)->assertOk()->assertJsonPath('result', true);
    expect(Order::count())->toBe(3);
});

test('an Idempotency-Key over 128 characters is refused', function () {
    orderingStore();
    $customer = createStoreUser('customer');
    customerCartWithOneLine($customer);

    $this->withToken(storeApiToken($customer))->withHeader('Idempotency-Key', str_repeat('a', 129))->postJson('/api/v1/store/order/store', ['payment_type' => 'cash_on_delivery', 'delivery_date' => validDeliveryDate()])
        ->assertUnprocessable()->assertJsonPath('errors.idempotency_key.0', __('api.order.idempotency_key_invalid'));

    expect(Order::count())->toBe(0);
});

// ---- BR-20261101-A: cart notes ------------------------------------------------------------------

test('carts/add clears a note sent empty, null or blank and keeps it when the key is absent', function (array $clearing) {
    orderingStore();
    $customer = createStoreUser('customer');
    $other = createStoreUser('customer');
    $product = createStoreProduct();
    $otherLine = Cart::create(['user_id' => $other->id, 'owner_id' => 0, 'product_id' => $product->id, 'quantity' => 1, 'variation' => '', 'price' => 2.5, 'item_notes' => 'not yours']);
    $token = storeApiToken($customer);
    $add = fn (array $extra) => $this->withToken($token)->postJson('/api/v1/store/carts/add', ['id' => $product->id, 'variant' => '', 'quantity' => 1, ...$extra])->assertOk()->assertJsonPath('result', true);

    $add(['item_notes' => 'keep me']);
    expect(Cart::where('user_id', $customer->id)->sole()->item_notes)->toBe('keep me');

    app('auth')->forgetGuards();
    $add(['quantity' => 2]);
    expect(Cart::where('user_id', $customer->id)->sole()->item_notes)->toBe('keep me');

    app('auth')->forgetGuards();
    $add($clearing);
    expect(Cart::where('user_id', $customer->id)->sole()->item_notes)->toBeNull()
        ->and($otherLine->fresh()->item_notes)->toBe('not yours');
})->with([
    'empty string' => [['item_notes' => '']],
    'null' => [['item_notes' => null]],
    'spaces' => [['item_notes' => '   ']],
]);

test('carts/update-note changes and clears the note of the caller\'s own line only', function () {
    orderingStore();
    $customer = createStoreUser('customer');
    $stranger = createStoreUser('customer');
    $mine = customerCartWithOneLine($customer);
    $theirs = customerCartWithOneLine($stranger);
    $theirs->update(['item_notes' => 'private']);
    $token = storeApiToken($customer);

    $this->withToken($token)->postJson('/api/v1/store/carts/update-note', ['id' => $mine->id, 'item_notes' => ' fresh '])->assertOk()->assertJsonPath('result', true);
    expect($mine->fresh()->item_notes)->toBe('fresh');

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/api/v1/store/carts/update-note', ['id' => $mine->id, 'item_notes' => ''])->assertOk();
    expect($mine->fresh()->item_notes)->toBeNull();

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/api/v1/store/carts/update-note', ['id' => $theirs->id, 'item_notes' => 'mine now'])->assertNotFound();
    expect($theirs->fresh()->item_notes)->toBe('private');

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/api/v1/store/carts/update-note', ['id' => $mine->id])->assertUnprocessable();
    app('auth')->forgetGuards();
    $this->flushHeaders()->postJson('/api/v1/store/carts/update-note', ['id' => $mine->id, 'item_notes' => 'x'])->assertUnauthorized();
});

// ---- BR-20261103-C.1: the customer list --------------------------------------------------------

test('representatives/get-customers pages the list when asked and returns it whole when not', function () {
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => true])->save();
    foreach (range(1, 7) as $number) {
        createStoreUser('customer')->forceFill(['name' => 'Shop '.$number])->save();
    }
    createStoreUser('customer')->forceFill(['name' => 'Other 1'])->save();
    $token = storeApiToken($rep);

    $whole = $this->withToken($token)->getJson('/api/v1/store/representatives/get-customers')->assertOk();
    expect($whole->json('data'))->toHaveCount(8)->and($whole->json())->not->toHaveKey('meta');

    app('auth')->forgetGuards();
    $page = $this->withToken($token)->getJson('/api/v1/store/representatives/get-customers?per_page=3&page=3&name=Shop')->assertOk();
    expect($page->json('data'))->toHaveCount(1)
        ->and($page->json('meta'))->toBe(['current_page' => 3, 'last_page' => 3, 'per_page' => 3, 'total' => 7]);

    app('auth')->forgetGuards();
    $first = $this->withToken($token)->getJson('/api/v1/store/representatives/get-customers?per_page=3&name=Shop')->assertOk();
    expect($first->json('data'))->toHaveCount(3)->and($first->json('data.0'))->toHaveKeys(['id', 'name', 'otajerID', 'company_name']);

    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/store/representatives/get-customers?per_page=500')->assertUnprocessable();
});

test('representatives/get-customers refuses an account that is not a representative', function () {
    $customer = createStoreUser('customer');

    $this->withToken(storeApiToken($customer))->getJson('/api/v1/store/representatives/get-customers?per_page=3')->assertForbidden();
});

// ---- BR-20261103-B.3: no data changes on GET ---------------------------------------------------

test('wishlist add and remove work with POST', function () {
    $customer = createStoreUser('customer');
    $stranger = createStoreUser('customer');
    $product = createStoreProduct();
    Wishlist::create(['user_id' => $stranger->id, 'product_id' => $product->id]);
    $token = storeApiToken($customer);

    $this->withToken($token)->postJson('/api/v1/store/wishlists-add-product', ['product_id' => $product->id])->assertOk()->assertJsonPath('is_in_wishlist', true);
    expect(Wishlist::where('user_id', $customer->id)->count())->toBe(1);

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/api/v1/store/wishlists-remove-product', ['product_id' => $product->id])->assertOk()->assertJsonPath('is_in_wishlist', false);
    expect(Wishlist::where('user_id', $customer->id)->count())->toBe(0)->and(Wishlist::where('user_id', $stranger->id)->count())->toBe(1);
});

test('a shipping address is deleted with POST or DELETE, and only the caller\'s own', function (string $method) {
    $customer = createStoreUser('customer');
    $stranger = createStoreUser('customer');
    $mine = addStoreAddress($customer->id);
    $theirs = addStoreAddress($stranger->id);
    $token = storeApiToken($customer);

    $this->withToken($token)->json($method, '/api/v1/store/user/shipping/delete/'.$theirs)->assertOk()->assertJsonPath('result', false);
    expect(Address::find($theirs))->not->toBeNull();

    app('auth')->forgetGuards();
    $this->withToken($token)->json($method, '/api/v1/store/user/shipping/delete/'.$mine)->assertOk()->assertJsonPath('result', true);
    expect(Address::find($mine))->toBeNull();
})->with(['POST', 'DELETE']);

test('logout and account deletion work with POST', function () {
    $customer = createStoreUser('customer');
    $customer->forceFill(['device_token' => 'fcm-token'])->save();
    $token = storeApiToken($customer);

    $this->withToken($token)->postJson('/api/v1/store/auth/logout')->assertOk()->assertJsonPath('result', true);

    expect($customer->fresh()->device_token)->toBeNull();
    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/api/v1/store/auth/logout')->assertUnauthorized();

    $leaving = createStoreUser('customer');
    $this->withToken(storeApiToken($leaving))->postJson('/api/v1/store/auth/account-deletion')->assertOk()->assertJsonPath('result', true);
    expect(DB::table('users')->where('id', $leaving->id)->count())->toBe(0)->and(DB::table('users')->where('id', $customer->id)->count())->toBe(1);
});

// ---- BR-20261102-D: the push token --------------------------------------------------------------

test('update-device-token stores the token under either name and clears it when empty', function () {
    $customer = createStoreUser('customer');
    $other = createStoreUser('customer');
    $other->forceFill(['device_token' => 'theirs'])->save();
    $token = storeApiToken($customer);

    $this->withToken($token)->postJson('/api/v1/store/profile/update-device-token', ['device_token' => 'fcm-1'])->assertOk()->assertJsonPath('result', true);
    expect($customer->fresh()->device_token)->toBe('fcm-1');

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/api/v1/store/profile/update-device-token', ['firebase_token' => 'fcm-2'])->assertOk();
    expect($customer->fresh()->device_token)->toBe('fcm-2');

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/api/v1/store/profile/update-device-token', ['device_token' => str_repeat('a', 256)])->assertUnprocessable();
    expect($customer->fresh()->device_token)->toBe('fcm-2');

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/api/v1/store/profile/update-device-token', ['device_token' => ''])->assertOk();
    expect($customer->fresh()->device_token)->toBeNull()->and($other->fresh()->device_token)->toBe('theirs');
});

test('login and POST auth/info store the push token', function () {
    $customer = createStoreUser('customer', 'push@example.com', 'correct-password');

    $login = $this->postJson('/api/v1/store/auth/login', ['email' => 'push@example.com', 'password' => 'correct-password', 'login_by' => 'email', 'firebase_token' => 'fcm-login'])->assertOk();
    expect($customer->fresh()->device_token)->toBe('fcm-login');

    $this->postJson('/api/v1/store/auth/info', ['access_token' => $login->json('access_token'), 'device_token' => 'fcm-info'])->assertOk();
    expect($customer->fresh()->device_token)->toBe('fcm-info');
});

// ---- BR-20261103-C.2: thumbnails ----------------------------------------------------------------

test('a large upload gets a small thumbnail and the original is left alone', function () {
    $directory = public_path('uploads/all');
    File::ensureDirectoryExists($directory);
    $name = 'uploads/all/thumb-test-'.uniqid().'.png';
    $image = imagecreatetruecolor(1600, 1200);
    imagefilledrectangle($image, 0, 0, 800, 600, imagecolorallocate($image, 200, 30, 30));
    imagepng($image, public_path($name), 0);
    imagedestroy($image);
    $upload = Upload::forceCreate(['file_original_name' => 'big', 'file_name' => $name, 'extension' => 'png', 'type' => 'image', 'file_size' => filesize(public_path($name)), 'user_id' => 1]);

    try {
        $url = app(ThumbnailService::class)->urlFor($upload);
        $path = public_path('uploads/thumbs/'.$upload->id.'-400.'.(function_exists('imagewebp') ? 'webp' : 'jpg'));

        expect($url)->toContain('uploads/thumbs/'.$upload->id.'-400.')
            ->and(is_file($path))->toBeTrue()
            ->and(getimagesize($path)[0])->toBe(400)
            ->and(getimagesize($path)[1])->toBe(300)
            ->and(getimagesize($path)['mime'])->toBe(function_exists('imagewebp') ? 'image/webp' : 'image/jpeg')
            ->and(filesize($path))->toBeLessThan(filesize(public_path($name)) / 10)
            ->and(is_file(public_path($name)))->toBeTrue()
            ->and(thumbnail_asset($upload->id))->toBe($url)
            ->and(uploaded_asset($upload->id))->toContain($name);
    } finally {
        @unlink(public_path($name));
        File::delete(File::glob(public_path('uploads/thumbs/'.$upload->id.'-*')));
    }
});

test('a heavy upload narrower than a thumbnail is re-encoded at its own size, not enlarged', function () {
    $directory = public_path('uploads/all');
    File::ensureDirectoryExists($directory);
    $name = 'uploads/all/thumb-narrow-'.uniqid().'.png';
    $image = imagecreatetruecolor(300, 2000);
    imagepng($image, public_path($name), 0);
    $upload = Upload::forceCreate(['file_original_name' => 'narrow', 'file_name' => $name, 'extension' => 'png', 'type' => 'image', 'file_size' => filesize(public_path($name)), 'user_id' => 1]);

    try {
        expect(filesize(public_path($name)))->toBeGreaterThan(150 * 1024)
            ->and(app(ThumbnailService::class)->urlFor($upload))->toContain('uploads/thumbs/'.$upload->id.'-400.');
        $info = getimagesize(public_path('uploads/thumbs/'.$upload->id.'-400.'.(function_exists('imagewebp') ? 'webp' : 'jpg')));
        expect([$info[0], $info[1]])->toBe([300, 2000]);
    } finally {
        @unlink(public_path($name));
        File::delete(File::glob(public_path('uploads/thumbs/'.$upload->id.'-*')));
    }
});

test('an upload that is not a local image keeps its original url', function () {
    $linked = Upload::forceCreate(['file_original_name' => 'cdn', 'file_name' => null, 'extension' => 'png', 'type' => 'image', 'file_size' => 1, 'user_id' => 1]);
    $linked->forceFill(['external_link' => 'https://cdn.example.com/a.png'])->save();
    $missing = Upload::forceCreate(['file_original_name' => 'gone', 'file_name' => 'uploads/all/does-not-exist.png', 'extension' => 'png', 'type' => 'image', 'file_size' => 1, 'user_id' => 1]);

    expect(app(ThumbnailService::class)->urlFor($linked))->toBeNull()
        ->and(thumbnail_asset($linked->id))->toBe('https://cdn.example.com/a.png')
        ->and(app(ThumbnailService::class)->urlFor($missing))->toBeNull()
        ->and(thumbnail_asset($missing->id))->toBe(uploaded_asset($missing->id))
        ->and(thumbnail_asset(999999))->toBe(static_asset('assets/img/placeholder.jpg'));
});

// ---- BR-20261102-B.2: the terms and privacy text for the sign-up screen ------------------------

test('the terms and privacy policy are served by the api, each with its own text', function () {
    Page::forceCreate(['type' => 'terms_conditions_page', 'title' => 'Terms', 'slug' => 'terms', 'content' => '<p>Our terms</p>']);
    Page::forceCreate(['type' => 'privacy_policy_page', 'title' => 'Privacy', 'slug' => 'privacy-policy', 'content' => '<p>Our privacy</p>']);
    Page::forceCreate(['type' => 'return_policy_page', 'title' => 'Returns', 'slug' => 'return-policy', 'content' => '<p>Returns</p>']);

    $this->getJson('/api/v1/store/policies/terms')->assertOk()->assertExactJson(['data' => [['content' => '<p>Our terms</p>']], 'success' => true, 'status' => 200]);
    $this->getJson('/api/v1/store/policies/privacy')->assertOk()->assertExactJson(['data' => [['content' => '<p>Our privacy</p>']], 'success' => true, 'status' => 200]);
});

test('an order stored with no delivery date shows null, not 1970', function () {
    orderingStore();
    $customer = createStoreUser('customer');
    $blank = createStoreOrder($customer, ['delivery_date' => '1970-01-01']);
    $dated = createStoreOrder($customer, ['delivery_date' => '2030-03-04']);
    $token = storeApiToken($customer);

    expect($this->withToken($token)->getJson('/api/v1/store/purchase-history-details/'.$blank->id)->assertOk()->json('data.0.delivery_date'))->toBeNull();
    app('auth')->forgetGuards();
    expect($this->withToken($token)->getJson('/api/v1/store/purchase-history-details/'.$dated->id)->assertOk()->json('data.0.delivery_date'))->toBe('04-03-2030');
});

// ---- BR-20261104-A.1: the minimum order and its fee line ----------------------------------------

/**
 * Turn on the minimum order of EUR 50 with a EUR 5 fee product (21 percent VAT)
 * that is unpublished and has no stock, as a fee product usually is.
 */
function minimumOrderFee(?int $feeProductId = null): int
{
    $fee = $feeProductId ?? createStoreProduct(['unit_price' => 5, 'tax' => 21, 'published' => 0, 'current_stock' => 0, 'name' => 'Small order fee'])->id;
    storeSetting('minimum_order_amount_check', 1);
    storeSetting('minimum_order_amount', 50);
    storeSetting('default_specific_product', (string) $fee);

    return $fee;
}

/** A customer's cart of `$quantity` EUR 10 products with 9 percent VAT. */
function smallCustomerCart(StoreUser $customer, int $quantity = 3): Cart
{
    $product = createStoreProduct(['unit_price' => 10, 'tax' => 9, 'cash_on_delivery' => 1]);

    return Cart::create(['user_id' => $customer->id, 'owner_id' => 0, 'product_id' => $product->id, 'quantity' => $quantity, 'variation' => '', 'price' => 10, 'shipping_type' => 'home_delivery', 'address_id' => addStoreAddress($customer->id)]);
}

function placeStoreApiOrder(StoreUser $user): TestResponse
{
    return test()->withToken(storeApiToken($user))->postJson('/api/v1/store/order/store', ['payment_type' => 'cash_on_delivery', 'additional_info' => '', 'delivery_date' => validDeliveryDate()]);
}

test('an order below the minimum is placed with the fee line, which takes no stock and survives being unpublished', function () {
    orderingStore();
    $fee = minimumOrderFee();
    $customer = createStoreUser('customer');
    $line = smallCustomerCart($customer);
    smallCustomerCart(createStoreUser('customer')); // another account's cart: untouched

    placeStoreApiOrder($customer)->assertOk()->assertJsonPath('result', true);

    $order = Order::sole();
    $lines = $order->orderDetails()->orderBy('id')->get();
    expect($lines->map(fn ($detail) => [$detail->product_id, (int) $detail->quantity, (float) $detail->price, (float) $detail->tax, (float) $detail->tax_amount])->all())
        ->toEqual([[$line->product_id, 3, 30.0, 9.0, 2.7], [$fee, 1, 5.0, 21.0, 1.05]])
        ->and((float) $order->grand_total)->toEqual(38.75) // 30 + 2.70 + 5 + 1.05
        ->and($order->additional_info)->toBe('')
        ->and(DB::table('product_stocks')->where('product_id', $fee)->value('qty'))->toBe(0)
        ->and(Cart::where('user_id', $customer->id)->exists())->toBeFalse()
        ->and(Cart::count())->toBe(1);
});

test('a representative\'s order below the minimum gets the fee line for the picked customer', function () {
    orderingStore();
    $fee = minimumOrderFee();
    $customer = createStoreUser('customer');
    $rep = repWithCartFor($customer);

    placeStoreApiOrder($rep)->assertOk()->assertJsonPath('result', true);

    $order = Order::sole();
    expect($order->orderDetails()->pluck('product_id')->all())->toContain($fee)
        ->and((int) $order->for_customer)->toBe($customer->id)
        ->and(json_decode($order->shipping_address, true)['name'])->toBe($customer->name);
});

test('an order at or above the minimum gets no fee line', function () {
    orderingStore();
    minimumOrderFee();
    $customer = createStoreUser('customer');
    $line = smallCustomerCart($customer, 5); // EUR 50 before VAT

    placeStoreApiOrder($customer)->assertOk()->assertJsonPath('result', true);

    expect(Order::sole()->orderDetails()->pluck('product_id')->all())->toBe([$line->product_id]);
});

test('an order below the minimum with no fee product to add is refused with 422 and changes nothing', function () {
    orderingStore();
    minimumOrderFee(999999);
    $customer = createStoreUser('customer');
    smallCustomerCart($customer);

    placeStoreApiOrder($customer)->assertUnprocessable()->assertExactJson([
        'combined_order_id' => 0,
        'result' => false,
        'message' => __('api.order.below_minimum', ['amount' => format_price(50)]),
        'errors' => ['minimum_order_amount' => [__('api.order.below_minimum', ['amount' => format_price(50)])]],
    ]);

    expect(Order::count())->toBe(0)->and(Cart::where('user_id', $customer->id)->count())->toBe(1);
});

test('a refused order below the minimum leaves no fee line in the cart', function () {
    orderingStore();
    minimumOrderFee();
    $customer = createStoreUser('customer');
    $line = smallCustomerCart($customer);
    DB::table('product_stocks')->where('product_id', $line->product_id)->update(['qty' => 1]);

    placeStoreApiOrder($customer)->assertOk()->assertJsonPath('result', false);

    expect(Order::count())->toBe(0)->and(Cart::where('user_id', $customer->id)->pluck('product_id')->all())->toBe([$line->product_id]);
});

test('the cart summary counts the fee line in its total, as the order charges it', function () {
    orderingStore();
    minimumOrderFee();
    $customer = createStoreUser('customer');
    smallCustomerCart($customer);

    $summary = $this->withToken(storeApiToken($customer))->getJson('/api/v1/store/cart-summary')->assertOk()->json();

    expect(collect($summary)->only(['sub_total', 'tax', 'delivery_cost', 'grand_total', 'grand_total_value'])->all())->toEqual([
        'sub_total' => format_price(30),
        'tax' => format_price(3.75), // 2.70 on the products, 1.05 on the fee
        'delivery_cost' => format_price(5),
        'grand_total' => format_price(38.75),
        'grand_total_value' => 38.75,
    ]);

    app('auth')->forgetGuards();
    placeStoreApiOrder($customer)->assertOk();
    expect((float) Order::sole()->grand_total)->toEqual(38.75);
});

test('the web checkout below the minimum also places the order with the fee line', function () {
    orderingStore();
    $fee = minimumOrderFee();
    $customer = createStoreUser('customer');
    smallCustomerCart($customer);
    actingAsStoreUser($customer);

    $this->post(route('payment.checkout'), ['payment_option' => 'cash_on_delivery', 'additional_info' => ''])->assertRedirect();

    expect(Order::sole()->orderDetails()->pluck('product_id')->all())->toContain($fee);
});

// ---- BR-20261104-A.2: the VAT percent ----------------------------------------------------------

test('a product taxed in the back office VAT section shows that percent in the cart, the product and the order', function () {
    orderingStore();
    $customer = createStoreUser('customer');
    $line = smallCustomerCart($customer);
    $product = $line->product;
    $product->forceFill(['tax' => 0, 'published' => 1, 'approved' => 1])->save();
    DB::table('product_taxes')->insert(['product_id' => $product->id, 'tax_id' => 1, 'tax' => 9, 'tax_type' => 'percent']);
    $token = storeApiToken($customer);

    $row = $this->withToken($token)->postJson('/api/v1/store/carts')->assertOk()->json('0.cart_items.0');
    expect([$row['tax_percent'], $row['tax_amount'], $row['price']])->toEqual([9, 2.7, 10]);

    app('auth')->forgetGuards();
    $detail = $this->withToken($token)->getJson('/api/v1/store/products/'.$product->id)->assertOk()->json('data.0');
    expect([$detail['tax'], $detail['tax_type'], $detail['price_includes_tax']])->toEqual([9, 'percent', true]);

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/api/v1/store/order/store', ['payment_type' => 'cash_on_delivery', 'delivery_date' => validDeliveryDate()])->assertOk()->assertJsonPath('result', true);
    $order = Order::sole();
    expect($order->orderDetails()->get(['tax', 'tax_amount'])->map(fn ($detail) => [(float) $detail->tax, (float) $detail->tax_amount])->all())->toEqual([[9.0, 2.7]]);

    app('auth')->forgetGuards();
    $items = $this->withToken($token)->getJson('/api/v1/store/purchase-history-items/'.$order->id)->assertOk()->json('data');
    expect([$items[0]['tax_percent'], $items[0]['tax']])->toEqual([9, format_price(2.7)]);
});

test('a product taxed by the Otajer feed shows its percent and says its prices are before VAT', function () {
    orderingStore();
    $product = createStoreProduct(['tax' => 21, 'published' => 1, 'approved' => 1]);

    $detail = $this->withToken(storeApiToken(createStoreUser('customer')))->getJson('/api/v1/store/products/'.$product->id)->assertOk()->json('data.0');

    expect([$detail['tax'], $detail['tax_type'], $detail['price_includes_tax']])->toEqual([21, 'percent', false]);
});

// ---- BR-20261104-A.3: an unknown variant -------------------------------------------------------

test('carts/add refuses a variant the product does not have with 422 and adds nothing', function (bool $verified) {
    orderingStore();
    $customer = createStoreUser('customer', verified: true);
    $customer->forceFill(['admin_verified' => $verified])->save();
    $product = createStoreProduct(['published' => 1]);

    $this->withToken(storeApiToken($customer))
        ->postJson('/api/v1/store/carts/add', ['id' => $product->id, 'variant' => 'NoSuchVariant', 'quantity' => 1])
        ->assertUnprocessable()
        ->assertExactJson(['result' => false, 'message' => __('api.cart.variant_not_found'), 'errors' => ['variant' => [__('api.cart.variant_not_found')]]]);

    expect(Cart::count())->toBe(0);
})->with(['verified customer' => true, 'unverified customer' => false]);

// ---- BR-20261104-A.4: an order line keeps its product ------------------------------------------

test('an order line keeps naming its own product after the product is duplicated and the copy renamed', function () {
    orderingStore();
    $customer = createStoreUser('customer');
    $product = createStoreProduct(['name' => 'VAT Test Chickpeas 400 g', 'serial' => 'MAT-1']);
    $order = createStoreOrder($customer);
    DB::table('order_details')->insert(['order_id' => $order->id, 'seller_id' => $product->user_id, 'product_id' => $product->id, 'product_serial' => 'MAT-1', 'price' => 10, 'tax' => 0, 'quantity' => 1]);
    createStoreProduct(['name' => 'Small order fee', 'serial' => 'MAT-1']); // a copy made before duplicates lost the serial

    $item = $this->withToken(storeApiToken($customer))->getJson('/api/v1/store/purchase-history-items/'.$order->id)->assertOk()->json('data.0');

    expect([$item['product_id'], $item['product_name'], $item['mat_id']])->toBe([$product->id, 'VAT Test Chickpeas 400 g', (string) $product->id]);
});

// ---- BR-20261104-A.5: one shape for the cart summary -------------------------------------------

test('the summary of an empty cart has the same types as a filled one', function () {
    orderingStore();
    $customer = createStoreUser('customer');
    $token = storeApiToken($customer);

    $empty = $this->withToken($token)->getJson('/api/v1/store/cart-summary')->assertOk()->json();
    expect(collect($empty)->only(['sub_total', 'tax', 'shipping_cost', 'discount', 'grand_total', 'grand_total_value', 'coupon_code', 'coupon_applied', 'delivery_cost'])->all())->toEqual([
        'sub_total' => format_price(0), 'tax' => format_price(0), 'shipping_cost' => format_price(0), 'discount' => format_price(0),
        'grand_total' => format_price(0), 'grand_total_value' => 0, 'coupon_code' => null, 'coupon_applied' => false, 'delivery_cost' => format_price(0),
    ]);

    customerCartWithOneLine($customer);
    app('auth')->forgetGuards();
    $filled = $this->withToken($token)->getJson('/api/v1/store/cart-summary')->assertOk()->json();
    // grand_total_value is a JSON number in both; a whole amount is written without its .0 either way.
    expect(array_map(get_debug_type(...), Arr::except($filled, 'grand_total_value')))->toEqual(array_map(get_debug_type(...), Arr::except($empty, 'grand_total_value')))
        ->and($filled['grand_total_value'])->toBeNumeric();
});

// ---- BR-20261105-A.2: the stars sort and filter -------------------------------------------------

/**
 * A category, a brand and five products of that brand in that category, graded
 * (stars) 5, 5, 3, none and 0, so that two share a grade, one has no collection
 * at all and one is graded 0 explicitly. Names run against the grades.
 *
 * @return array{category: int, brand: int, products: array<string, Product>}
 */
function starGradedProducts(): array
{
    orderingStore();
    $category = DB::table('categories')->insertGetId(['name' => 'Snacks', 'slug' => 'snacks', 'level' => 0, 'parent_id' => 0, 'digital' => 0]);
    $brand = DB::table('brands')->insertGetId(['name' => 'Acme', 'slug' => 'acme', 'top' => 1]);
    $products = [];

    foreach (['Beta' => 5, 'Alpha' => 5, 'Gamma' => 3, 'Delta' => null, 'Epsilon' => 0] as $name => $stars) {
        $collection = $stars === null ? null : ProductCollection::create(['name' => "Grade $stars", 'serial' => "G$name", 'amount' => $stars])->id;
        $products[$name] = createStoreProduct(['name' => $name, 'category_id' => $category, 'brand_id' => $brand, 'collection_id' => $collection, 'published' => 1, 'approved' => 1, 'featured' => 1, 'isNewProduct' => 1]);
    }

    return ['category' => $category, 'brand' => $brand, 'products' => $products];
}

/** @return list<string> the names a product list answered, in order */
function listedNames(TestResponse $response): array
{
    return $response->assertOk()->collect('data')->pluck('name')->all();
}

test('sort_key=stars_high_to_low lists the highest store grade first, ties by name, on search, category and brand', function () {
    $store = starGradedProducts();
    $query = '?sort_key=stars_high_to_low';

    $search = $this->getJson("/api/v1/store/products/search?categories={$store['category']}&brands={$store['brand']}&sort_key=stars_high_to_low")->assertOk();
    expect(listedNames($search))->toBe(['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon'])
        ->and($search->collect('data')->pluck('stars')->all())->toBe([5, 5, 3, 0, 0]);

    expect(listedNames($this->getJson("/api/v1/store/products/category/{$store['category']}$query")))->toBe(['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon'])
        ->and(listedNames($this->getJson("/api/v1/store/products/sub-category/{$store['category']}$query")))->toBe(['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon'])
        ->and(listedNames($this->getJson("/api/v1/store/products/brand/{$store['brand']}$query")))->toBe(['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon']);
});

test('sort_key=top_rated still sorts by the customers\' review rating, not by stars', function () {
    $store = starGradedProducts();
    $store['products']['Delta']->forceFill(['rating' => 4.5])->save();
    $store['products']['Gamma']->forceFill(['rating' => 2])->save();

    $names = listedNames($this->getJson("/api/v1/store/products/search?categories={$store['category']}&sort_key=top_rated"));

    expect(array_slice($names, 0, 2))->toBe(['Delta', 'Gamma']);
});

test('min_stars keeps the products graded at least that high, and ignores a value that is not a number', function (string $minimum, array $expected) {
    $store = starGradedProducts();

    expect(listedNames($this->getJson("/api/v1/store/products/search?categories={$store['category']}&sort_key=stars_high_to_low&min_stars=$minimum")))->toBe($expected)
        ->and(listedNames($this->getJson("/api/v1/store/products/category/{$store['category']}?sort_key=stars_high_to_low&min_stars=$minimum")))->toBe($expected)
        ->and(listedNames($this->getJson("/api/v1/store/products/brand/{$store['brand']}?sort_key=stars_high_to_low&min_stars=$minimum")))->toBe($expected);
})->with([
    'four' => ['4', ['Alpha', 'Beta']],
    'three' => ['3', ['Alpha', 'Beta', 'Gamma']],
    'six, which nothing reaches' => ['6', []],
    'zero keeps all' => ['0', ['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon']],
    'not a number' => ['many', ['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon']],
]);

test('without a sort_key the lists are ordered as before', function () {
    $store = starGradedProducts();

    // The search sorts by name, as it always did.
    expect(listedNames($this->getJson("/api/v1/store/products/search?categories={$store['category']}")))->toBe(['Alpha', 'Beta', 'Delta', 'Epsilon', 'Gamma'])
        ->and(listedNames($this->getJson("/api/v1/store/products/search?categories={$store['category']}&sort_key=nonsense")))->toBe(['Alpha', 'Beta', 'Delta', 'Epsilon', 'Gamma']);

    // A category list puts the graded products first by the collection's amount, as it always did.
    $category = listedNames($this->getJson("/api/v1/store/products/category/{$store['category']}"));
    expect(array_slice($category, 0, 3))->toContain('Alpha', 'Beta')->and($category[2])->toBe('Gamma');
});

// ---- BR-20261105-A.3: a brand's categories ------------------------------------------------------

test('every brand row names the categories where the brand has products a shopper can see', function () {
    orderingStore();
    [$snacks, $drinks, $dairy] = array_map(fn (string $name) => DB::table('categories')->insertGetId(['name' => $name, 'slug' => strtolower($name), 'level' => 0, 'parent_id' => 0, 'digital' => 0]), ['Snacks', 'Drinks', 'Dairy']);
    [$acme, $zed, $empty] = array_map(fn (string $name) => DB::table('brands')->insertGetId(['name' => $name, 'slug' => strtolower($name), 'top' => 1]), ['Acme', 'Zed', 'Empty']);
    $visible = fn (int $brand, int $category, array $extra = []) => createStoreProduct(['brand_id' => $brand, 'category_id' => $category, 'published' => 1, 'approved' => 1, ...$extra]);

    $visible($acme, $drinks);
    $visible($acme, $snacks);
    $visible($acme, $snacks); // two products in one category: the category is named once
    $visible($zed, $drinks);
    // None of these is a product the app can list, so none of them counts.
    $visible($acme, $dairy, ['published' => 0]);
    $visible($zed, $dairy, ['approved' => 0]);
    $visible($zed, $dairy, ['digital' => 1]);
    $visible($zed, $dairy, ['added_by' => 'seller']);
    Product::factory()->create(['brand_id' => $zed, 'category_id' => $dairy, 'published' => 1, 'approved' => 1]); // no stock row

    foreach (['brands', 'brands/top', 'filter/brands'] as $path) {
        $rows = $this->getJson("/api/v1/store/$path")->assertOk()->collect('data')->keyBy('id');

        expect($rows[$acme]['category_ids'])->toBe([$snacks, $drinks])
            ->and($rows[$zed]['category_ids'])->toBe([$drinks])
            ->and($rows[$empty]['category_ids'])->toBe([]);
    }

    // The ids are the ones the app used to find by asking category by category.
    foreach ([[$acme, $snacks, 2], [$acme, $drinks, 1], [$acme, $dairy, 0], [$zed, $drinks, 1], [$zed, $dairy, 0], [$zed, $snacks, 0]] as [$brand, $category, $count]) {
        $search = $this->getJson("/api/v1/store/products/search?categories=$category&brands=$brand")->assertOk();
        expect($search->collect('data'))->toHaveCount($count);
    }
});

test('a paged brand list names the categories of the brands on that page only', function () {
    orderingStore();
    $category = DB::table('categories')->insertGetId(['name' => 'Snacks', 'slug' => 'snacks', 'level' => 0, 'parent_id' => 0, 'digital' => 0]);
    $brands = array_map(fn (string $name) => DB::table('brands')->insertGetId(['name' => $name, 'slug' => strtolower($name), 'top' => 0]), ['Acme', 'Zed']);
    createStoreProduct(['brand_id' => $brands[1], 'category_id' => $category, 'published' => 1, 'approved' => 1]);

    $page = $this->getJson('/api/v1/store/brands?per_page=1&page=2')->assertOk();

    expect($page->json('data.0.id'))->toBe($brands[1])
        ->and($page->json('data.0.category_ids'))->toBe([$category])
        ->and($page->json('meta.per_page'))->toBe(1);
});

// ---- BR-20261105-A.5: smaller performance points ------------------------------------------------

test('the lists that rarely change carry an ETag and answer 304 to a repeat', function (string $path, bool $signedIn) {
    orderingStore();
    DB::table('categories')->insert(['name' => 'Snacks', 'slug' => 'snacks', 'level' => 0, 'parent_id' => 0, 'featured' => 1, 'digital' => 0]);
    DB::table('brands')->insert(['name' => 'Acme', 'slug' => 'acme', 'top' => 1]);
    storeSetting('home_categories', '[]');
    if ($signedIn) {
        $this->withToken(storeApiToken(createStoreUser('customer')));
    }

    $first = $this->getJson("/api/v1/store/$path")->assertOk();
    $etag = $first->headers->get('ETag');

    expect($etag)->not->toBeNull()->and($first->headers->get('Cache-Control'))->toContain('private');

    $this->getJson("/api/v1/store/$path", ['If-None-Match' => $etag])->assertNotModified();
})->with([
    'categories' => ['categories', false],
    'featured categories' => ['categories/featured', false],
    'home categories' => ['categories/home', false],
    'top categories' => ['categories/top', false],
    'sub-categories' => ['sub-categories/1', false],
    'brands' => ['brands', false],
    'top brands' => ['brands/top', false],
    'sliders' => ['sliders', false],
    'banners one' => ['banners-one', false],
    'banners two' => ['banners-two', false],
    'banners three' => ['banners-three', false],
    'payment types' => ['payment-types', true],
]);

test('a changed list gets a new ETag, so the stale one is answered with the new list and not a 304', function () {
    orderingStore();
    DB::table('brands')->insert(['name' => 'Acme', 'slug' => 'acme', 'top' => 1]);
    $etag = $this->getJson('/api/v1/store/brands')->assertOk()->headers->get('ETag');

    DB::table('brands')->insert(['name' => 'Zed', 'slug' => 'zed', 'top' => 1]);
    $changed = $this->getJson('/api/v1/store/brands', ['If-None-Match' => $etag])->assertOk();

    expect($changed->headers->get('ETag'))->not->toBe($etag)->and($changed->json('data'))->toHaveCount(2);
});

test('a product list takes an optional per_page of 1 to 50, and answers 10 rows as before without one', function (string $path) {
    $store = starGradedProducts();
    foreach (range(1, 7) as $n) {
        createStoreProduct(['name' => "Extra $n", 'category_id' => $store['category'], 'brand_id' => $store['brand'], 'published' => 1, 'approved' => 1, 'featured' => 1, 'isNewProduct' => 1]);
    }
    $path = str_replace(['{category}', '{brand}'], [(string) $store['category'], (string) $store['brand']], $path);
    $glue = str_contains($path, '?') ? '&' : '?';
    $page = fn (string $query) => $this->getJson($path.$glue.$query)->assertOk();

    // 12 products in all.
    expect($page('page=1')->collect('data'))->toHaveCount(10)
        ->and($page('page=1')->json('meta.per_page'))->toBe(10)
        ->and($page('per_page=5')->collect('data'))->toHaveCount(5)
        ->and($page('per_page=5')->json('meta.last_page'))->toBe(3)
        ->and($page('per_page=5&page=3')->collect('data'))->toHaveCount(2)
        ->and($page('per_page=1')->collect('data'))->toHaveCount(1)
        ->and($page('per_page=500')->json('meta.per_page'))->toBe(50)
        ->and($page('per_page=500')->collect('data'))->toHaveCount(12);

    foreach (['0', '-3', 'many', '2.5', ''] as $unusable) {
        expect($page('per_page='.$unusable)->json('meta.per_page'))->toBe(10);
    }
})->with([
    'all products' => ['/api/v1/store/products'],
    'search' => ['/api/v1/store/products/search?categories={category}'],
    'category' => ['/api/v1/store/products/category/{category}'],
    'brand' => ['/api/v1/store/products/brand/{brand}'],
    'in-house' => ['/api/v1/store/products/inhouse'],
    'new products' => ['/api/v1/store/products/new_products'],
    'featured' => ['/api/v1/store/products/featured'],
]);

test('the brand list takes per_page too', function () {
    orderingStore();
    foreach (range(1, 12) as $n) {
        DB::table('brands')->insert(['name' => "Brand $n", 'slug' => "brand-$n", 'top' => 0]);
    }

    expect($this->getJson('/api/v1/store/brands')->assertOk()->collect('data'))->toHaveCount(10)
        ->and($this->getJson('/api/v1/store/brands?per_page=4')->assertOk()->collect('data'))->toHaveCount(4)
        ->and($this->getJson('/api/v1/store/brands?per_page=100')->assertOk()->collect('data'))->toHaveCount(12);
});

test('a cart change answers the cart and its summary only when asked with with_cart, and as the two routes would', function () {
    orderingStore();
    $customer = createStoreUser('customer');
    $product = createStoreProduct(['unit_price' => 10]);
    $other = createStoreProduct(['unit_price' => 4]);
    $token = storeApiToken($customer);
    $add = fn (array $body) => $this->withToken($token)->postJson('/api/v1/store/carts/add', ['variant' => '', ...$body]);

    // Without the parameter, the answer is what it was.
    $plain = $add(['id' => $product->id, 'quantity' => 1])->assertOk();
    expect($plain->json())->toEqual(['result' => true, 'message' => __('api.cart.added'), 'offer_message' => null]);

    app('auth')->forgetGuards();
    $asked = $add(['id' => $other->id, 'quantity' => 2, 'with_cart' => 1])->assertOk();
    expect($asked->json('result'))->toBeTrue();

    app('auth')->forgetGuards();
    $cart = $this->withToken($token)->postJson('/api/v1/store/carts')->assertOk()->json();
    app('auth')->forgetGuards();
    $summary = $this->withToken($token)->getJson('/api/v1/store/cart-summary')->assertOk()->json();

    expect($asked->json('cart'))->toEqual($cart)
        ->and($asked->json('summary'))->toEqual($summary)
        ->and(collect($asked->json('cart.0.cart_items'))->pluck('quantity', 'product_id')->all())->toEqual([$product->id => 1, $other->id => 2])
        ->and($asked->json('summary.sub_total'))->toBe(format_price(18))
        ->and($asked->json('message'))->toBe(__('api.cart.added'));
});

test('change-quantity and removal carry the cart on request, and a refusal carries none', function () {
    orderingStore();
    $customer = createStoreUser('customer');
    $kept = customerCartWithOneLine($customer);
    $dropped = customerCartWithOneLine($customer);
    $token = storeApiToken($customer);

    $changed = $this->withToken($token)->postJson('/api/v1/store/carts/change-quantity', ['id' => $kept->id, 'quantity' => 4, 'with_cart' => true])->assertOk();
    expect(collect($changed->json('cart.0.cart_items'))->firstWhere('id', $kept->id)['quantity'])->toBe(4)
        ->and($changed->json('summary.sub_total'))->toBe(format_price(4 * 2.5 + 2.5));

    // Over the stock: refused, and the answer is exactly the refusal.
    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/api/v1/store/carts/change-quantity', ['id' => $kept->id, 'quantity' => 100000, 'with_cart' => true])
        ->assertOk()->assertExactJson(['result' => false, 'message' => __('api.cart.maximum_available')]);

    app('auth')->forgetGuards();
    $removed = $this->withToken($token)->deleteJson("/api/v1/store/carts/{$dropped->id}?with_cart=1")->assertOk();
    expect($removed->json('message'))->toBe(__('api.cart.removed'))
        ->and(collect($removed->json('cart.0.cart_items'))->pluck('id')->all())->toBe([$kept->id])
        ->and($removed->json('summary.sub_total'))->toBe(format_price(10));

    // A line that is not the caller's is a 404 with no cart in it, and a removal without the parameter is unchanged.
    app('auth')->forgetGuards();
    $this->withToken($token)->deleteJson('/api/v1/store/carts/999999?with_cart=1')->assertNotFound()->assertExactJson(['result' => false, 'message' => __('api.cart.item_not_found')]);
    app('auth')->forgetGuards();
    $this->withToken($token)->deleteJson("/api/v1/store/carts/{$kept->id}")->assertOk()->assertExactJson(['result' => true, 'message' => __('api.cart.removed')]);
});

test('the cart a change carries is empty when the change emptied it', function () {
    orderingStore();
    $customer = createStoreUser('customer');
    $line = customerCartWithOneLine($customer);

    $removed = $this->withToken(storeApiToken($customer))->deleteJson("/api/v1/store/carts/{$line->id}?with_cart=1")->assertOk();

    expect($removed->json('cart'))->toBe([])
        ->and($removed->json('summary.sub_total'))->toBe(format_price(0))
        ->and($removed->json('summary.grand_total_value'))->toEqual(0);
});

test('a customer who may not see prices gets the cart of a change with the prices blanked, as the cart routes give it', function () {
    orderingStore();
    $customer = createStoreUser('customer', verified: false);
    $product = createStoreProduct(['unit_price' => 10]);

    $response = $this->withToken(storeApiToken($customer))->postJson('/api/v1/store/carts/add', ['id' => $product->id, 'variant' => '', 'quantity' => 1, 'with_cart' => 1])->assertOk();

    expect($response->headers->get('X-Prices-Hidden'))->toBe('1')
        ->and($response->json('cart.0.cart_items.0.price'))->toBeNull()
        ->and($response->json('cart.0.cart_items.0.quantity'))->toBe(1)
        ->and($response->json('summary.sub_total'))->toBeNull()
        ->and($response->json('summary.grand_total'))->toBeNull()
        ->and($response->json('summary.price_hidden'))->toBeTrue();
});

test('a representative\'s own price on a line carries the cart on request', function () {
    orderingStore();
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => true, 'rep_discount_percentage' => 20])->save();
    $line = customerCartWithOneLine($rep);

    $changed = $this->withToken(storeApiToken($rep))->postJson('/api/v1/store/cart/update_item_price', ['id' => $line->id, 'rep_price' => 2.2, 'with_cart' => 1])->assertOk();

    expect($changed->json('message'))->toBe(__('api.cart.price_updated'))
        ->and($changed->json('cart.0.cart_items.0.rep_price'))->toEqual(2.2)
        ->and($changed->json('summary.sub_total'))->toBe(format_price(2.2));
});
