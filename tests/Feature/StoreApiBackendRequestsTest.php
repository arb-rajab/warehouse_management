<?php

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Page;
use App\Models\ProductCollection;
use App\Models\StoreUser;
use App\Models\Upload;
use App\Models\Wishlist;
use App\Services\ThumbnailService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The store API changes asked for in BR-20261103-A/B/C, BR-20261102-B/D and
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
