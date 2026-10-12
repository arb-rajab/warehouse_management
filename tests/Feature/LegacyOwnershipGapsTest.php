<?php

use App\Models\Address;
use App\Models\Coupon;
use App\Models\OrderDetail;
use App\Models\StoreUser;
use App\Models\Upload;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Store records that any signed-in account could reach by id: the seller app
 * API (no seller gate at all), the seller area's coupons and conversations,
 * the app API's addresses, refund requests and file library, and the web
 * checkout's address pick.
 */
function gapApi(StoreUser $user): mixed
{
    app('auth')->forgetGuards();

    return test()->withToken(storeApiToken($user));
}

function gapGuestApi(): mixed
{
    app('auth')->forgetGuards();

    return test()->withoutToken();
}

/** An encrypted id with no slash in it, which a route segment can carry. */
function gapEncrypted(int $id): string
{
    do {
        $encrypted = encrypt($id);
    } while (str_contains($encrypted, '/'));

    return $encrypted;
}

function gapConversation(StoreUser $sender, StoreUser $receiver): int
{
    return DB::table('conversations')->insertGetId([
        'sender_id' => $sender->id, 'receiver_id' => $receiver->id, 'title' => 'Hello',
        'sender_viewed' => 1, 'receiver_viewed' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function gapRefundTable(): void
{
    Schema::create('refund_requests', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('order_id');
        $table->unsignedBigInteger('order_detail_id');
        $table->unsignedBigInteger('seller_id')->nullable();
        $table->integer('seller_approval')->default(0);
        $table->text('reason')->nullable();
        $table->integer('admin_approval')->default(0);
        $table->integer('admin_seen')->default(0);
        $table->decimal('refund_amount', 20, 2)->default(0);
        $table->integer('refund_status')->default(0);
        $table->text('reject_reason')->nullable();
        $table->timestamps();
    });
}

function gapOrderDetail(StoreUser $customer, array $attributes = []): OrderDetail
{
    $seller = createStoreUser('seller');
    $product = createStoreProduct(['user_id' => $seller->id]);
    $order = createStoreOrder($customer, ['seller_id' => $seller->id]);

    return OrderDetail::forceCreate([
        'order_id' => $order->id, 'seller_id' => $seller->id, 'product_id' => $product->id, 'price' => 5, 'tax' => 1,
        'quantity' => 1, 'variation' => '', 'payment_status' => 'paid', 'delivery_status' => 'delivered', ...$attributes,
    ]);
}

/** @return list<RoutingRoute> */
function gapSellerApiRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (RoutingRoute $route) => str_contains($route->uri(), 'api/v2/seller/'),
    ));
}

test('every seller app API route refuses a guest and a non-seller account', function () {
    $routes = gapSellerApiRoutes();
    $customer = createStoreUser('customer');
    $guestStatuses = [];
    $customerStatuses = [];

    foreach ($routes as $route) {
        $verb = array_values(array_diff($route->methods(), ['HEAD']))[0];
        $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());

        expect($route->gatherMiddleware())->toContain('seller');

        $guestStatuses[] = gapGuestApi()->json($verb, $uri)->getStatusCode();
        $customerStatuses[] = gapApi($customer)->json($verb, $uri)->getStatusCode();
    }

    expect(count($routes))->toBeGreaterThan(20)
        ->and(array_unique($guestStatuses))->toBe([401])
        ->and(array_unique($customerStatuses))->toBe([404]);
});

test('a banned seller is refused on the seller app API', function () {
    $seller = createStoreUser('seller');
    $seller->forceFill(['banned' => 1])->save();

    gapApi($seller)->getJson('/temp/api/v2/seller/orders')->assertNotFound();
});

describe('seller coupons', function () {
    test("a seller cannot open, change or delete another seller's coupon", function () {
        $owner = createStoreUser('seller');
        $coupon = couponFor($owner, ['code' => 'OWNERS']);
        $noise = couponFor(createStoreUser('seller'), ['code' => 'NOISE']);
        actingAsStoreUser(createStoreUser('seller'));

        $form = [
            'type' => 'cart_base', 'code' => 'HACKED', 'discount' => 10, 'discount_type' => 'amount', 'min_buy' => 20, 'max_discount' => 10,
            'date_range' => '01-01-2026 - 02-01-2026', 'start_date' => strtotime('-1 day'), 'end_date' => strtotime('+1 day'),
            'details' => json_encode(['min_buy' => 20, 'max_discount' => 10]),
        ];

        $this->get('/temp/seller/coupon/'.gapEncrypted($coupon->id).'/edit')->assertForbidden();
        $this->put("/temp/seller/coupon/{$coupon->id}", $form)->assertForbidden();
        $this->post("/temp/seller/coupon/destroy/{$coupon->id}")->assertForbidden();
        $this->post('/temp/seller/coupon/get_form_edit', ['coupon_type' => 'cart_base', 'id' => $coupon->id])->assertForbidden();
        $this->post('/temp/seller/coupon/get_form_edit', ['coupon_type' => 'product_base', 'id' => $coupon->id])->assertForbidden();

        expect($coupon->fresh()->only(['code', 'discount']))->toEqual(['code' => 'OWNERS', 'discount' => 50])
            ->and(Coupon::count())->toBe(2)
            ->and($noise->fresh()->code)->toBe('NOISE');
    });

    test('a seller can reach and delete their own coupon', function () {
        $seller = actingAsStoreUser(createStoreUser('seller'));
        $coupon = couponFor($seller);
        $noise = couponFor(createStoreUser('seller'));

        expect($this->get('/temp/seller/coupon/'.gapEncrypted($coupon->id).'/edit')->getStatusCode())->not->toBe(403)
            ->and($this->post('/temp/seller/coupon/get_form_edit', ['coupon_type' => 'cart_base', 'id' => $coupon->id])->getStatusCode())->not->toBe(403);

        $this->post("/temp/seller/coupon/destroy/{$coupon->id}")->assertRedirect();

        expect(Coupon::find($coupon->id))->toBeNull()
            ->and(Coupon::find($noise->id))->not->toBeNull();
    });

    test('a guest and a customer are refused on every coupon action', function (string $callerType) {
        $coupon = couponFor(createStoreUser('seller'), ['code' => 'OWNERS']);
        if ($callerType !== 'guest') {
            actingAsStoreUser(createStoreUser($callerType));
        }

        $this->get('/temp/seller/coupon/'.gapEncrypted($coupon->id).'/edit')->assertNotFound();
        $this->put("/temp/seller/coupon/{$coupon->id}", ['code' => 'HACKED'])->assertNotFound();
        $this->post("/temp/seller/coupon/destroy/{$coupon->id}")->assertNotFound();
        $this->post('/temp/seller/coupon/get_form_edit', ['coupon_type' => 'cart_base', 'id' => $coupon->id])->assertNotFound();

        expect($coupon->fresh()->code)->toBe('OWNERS')->and(Coupon::count())->toBe(1);
    })->with(['guest', 'customer']);

    test('editing a coupon that does not exist returns 404', function () {
        actingAsStoreUser(createStoreUser('seller'));

        $this->get('/temp/seller/coupon/'.gapEncrypted(999999).'/edit')->assertNotFound();
        $this->post('/temp/seller/coupon/destroy/999999')->assertNotFound();
    });
});

describe('seller conversations', function () {
    test('a seller who is not in a conversation cannot post to it, open it or refresh it', function () {
        $customer = createStoreUser('customer');
        $conversationId = gapConversation($customer, createStoreUser('seller'));
        actingAsStoreUser(createStoreUser('seller'));

        $this->post('/temp/seller/conversations/message/store', ['conversation_id' => $conversationId, 'message' => 'intruder'])->assertForbidden();
        $this->get('/temp/seller/conversations/show/'.gapEncrypted($conversationId))->assertForbidden();
        $this->post('/temp/seller/conversations/refresh', ['id' => gapEncrypted($conversationId)])->assertForbidden();

        expect(DB::table('messages')->count())->toBe(0)
            ->and(DB::table('conversations')->where('id', $conversationId)->first()->receiver_viewed)->toBe(0);
    });

    test('the seller a conversation was started with can post to it', function () {
        $seller = createStoreUser('seller');
        $conversationId = gapConversation(createStoreUser('customer'), $seller);
        $noiseId = gapConversation(createStoreUser('customer'), createStoreUser('seller'));
        actingAsStoreUser($seller);

        $this->post('/temp/seller/conversations/message/store', ['conversation_id' => $conversationId, 'message' => 'hi'])->assertRedirect();

        expect(DB::table('messages')->where('conversation_id', $conversationId)->where('user_id', $seller->id)->count())->toBe(1)
            ->and(DB::table('messages')->where('conversation_id', $noiseId)->count())->toBe(0);
    });

    test('a guest and a customer are refused on every conversation action', function (string $callerType) {
        $conversationId = gapConversation(createStoreUser('customer'), createStoreUser('seller'));
        if ($callerType !== 'guest') {
            actingAsStoreUser(createStoreUser($callerType));
        }

        $this->post('/temp/seller/conversations/message/store', ['conversation_id' => $conversationId, 'message' => 'x'])->assertNotFound();
        $this->get('/temp/seller/conversations/show/'.gapEncrypted($conversationId))->assertNotFound();
        $this->post('/temp/seller/conversations/refresh', ['id' => gapEncrypted($conversationId)])->assertNotFound();

        expect(DB::table('messages')->count())->toBe(0);
    })->with(['guest', 'customer']);

    test('posting to a conversation that does not exist returns 404', function () {
        actingAsStoreUser(createStoreUser('seller'));

        $this->post('/temp/seller/conversations/message/store', ['conversation_id' => 999999, 'message' => 'x'])->assertNotFound();
    });

    test('the app API lets only the receiving seller answer, and only into the conversation named', function () {
        $receiver = createStoreUser('seller');
        $conversationId = gapConversation(createStoreUser('customer'), $receiver);
        $noiseId = gapConversation(createStoreUser('customer'), createStoreUser('seller'));
        $uri = '/temp/api/v2/seller/conversations/message/store';

        gapApi(createStoreUser('seller'))->postJson($uri, ['conversation_id' => $conversationId, 'message' => 'intruder'])->assertJsonPath('result', false);
        gapApi($receiver)->postJson($uri, ['conversation_id' => 999999, 'message' => 'lost'])->assertJsonPath('result', false);

        expect(DB::table('messages')->count())->toBe(0);

        gapApi($receiver)->postJson($uri, ['conversation_id' => $conversationId, 'message' => 'hello'])->assertJsonPath('result', true);

        expect(DB::table('messages')->where('conversation_id', $conversationId)->count())->toBe(1)
            ->and(DB::table('messages')->where('conversation_id', $noiseId)->count())->toBe(0);
    });
});

describe('app API addresses', function () {
    test("a customer cannot change, locate, default, delete or ship a cart to another customer's address", function () {
        $them = createStoreUser('customer');
        $theirs = addStoreAddress($them->id);
        $me = createStoreUser('customer');
        $mine = addStoreAddress($me->id);
        $cart = cartLine($me, createStoreUser('seller'), 1);
        $api = gapApi($me);

        $api->postJson('/temp/api/v2/user/shipping/update', ['id' => $theirs, 'address' => 'Hijacked 1', 'phone' => '1', 'state_id' => 1])->assertForbidden();
        $api->postJson('/temp/api/v2/user/shipping/update-location', ['id' => $theirs, 'latitude' => 1, 'longitude' => 2])->assertForbidden();
        $api->postJson('/temp/api/v2/user/shipping/make_default', ['id' => $theirs])->assertForbidden();
        $api->getJson("/temp/api/v2/user/shipping/delete/{$theirs}")->assertJsonPath('result', false);
        $api->postJson('/temp/api/v2/update-address-in-cart', ['address_id' => $theirs])->assertForbidden();

        expect(Address::find($theirs)->only(['address', 'latitude', 'set_default']))->toEqual(['address' => 'Damrak 1', 'latitude' => null, 'set_default' => 1])
            ->and(Address::find($mine)->set_default)->toBe(1)
            ->and($cart->fresh()->address_id)->toBe(0);
    });

    test('a customer can change, default, delete and ship a cart to their own address', function () {
        $me = createStoreUser('customer');
        $first = addStoreAddress($me->id);
        $second = addStoreAddress($me->id);
        $noise = addStoreAddress(createStoreUser('customer')->id);
        $cart = cartLine($me, createStoreUser('seller'), 1);
        $api = gapApi($me);

        $api->postJson('/temp/api/v2/user/shipping/update', ['id' => $first, 'address' => 'Changed 2', 'phone' => '1', 'state_id' => 1])->assertJsonPath('result', true);
        $api->postJson('/temp/api/v2/user/shipping/make_default', ['id' => $second])->assertJsonPath('result', true);
        $api->postJson('/temp/api/v2/update-address-in-cart', ['address_id' => $first])->assertJsonPath('result', true);

        expect(Address::find($first)->address)->toBe('Changed 2')
            ->and(Address::find($first)->set_default)->toBe(0)
            ->and(Address::find($second)->set_default)->toBe(1)
            ->and(Address::find($noise)->set_default)->toBe(1)
            ->and($cart->fresh()->address_id)->toBe($first);

        $api->getJson("/temp/api/v2/user/shipping/delete/{$first}")->assertJsonPath('result', true);

        expect(Address::find($first))->toBeNull()->and(Address::find($noise))->not->toBeNull();
    });

    test("a representative can manage the address of the customer they order for, and no one else's", function () {
        $rep = createStoreUser('customer');
        $rep->forceFill(['is_rep' => 1])->save();
        $customer = createStoreUser('customer');
        $customersAddress = addStoreAddress($customer->id);
        $strangersAddress = addStoreAddress(createStoreUser('customer')->id);
        $cart = cartLine($rep, createStoreUser('seller'), 1);
        $cart->forceFill(['for_customer' => $customer->id])->save();
        $api = gapApi($rep->fresh());

        $api->postJson('/temp/api/v2/user/shipping/update', ['id' => $strangersAddress, 'address' => 'Hijacked 1', 'phone' => '1', 'state_id' => 1])->assertForbidden();
        $api->postJson('/temp/api/v2/user/shipping/update', ['id' => $customersAddress, 'address' => 'Changed 2', 'phone' => '1', 'state_id' => 1])->assertJsonPath('result', true);

        expect(Address::find($strangersAddress)->address)->toBe('Damrak 1')
            ->and(Address::find($customersAddress)->address)->toBe('Changed 2');
    });

    test('a guest is refused on every address action', function () {
        $address = addStoreAddress(createStoreUser('customer')->id);
        $api = gapGuestApi();

        $api->postJson('/temp/api/v2/user/shipping/update', ['id' => $address, 'address' => 'Hijacked 1'])->assertUnauthorized();
        $api->postJson('/temp/api/v2/user/shipping/update-location', ['id' => $address, 'latitude' => 1])->assertUnauthorized();
        $api->postJson('/temp/api/v2/user/shipping/make_default', ['id' => $address])->assertUnauthorized();
        $api->getJson("/temp/api/v2/user/shipping/delete/{$address}")->assertUnauthorized();
        $api->postJson('/temp/api/v2/update-address-in-cart', ['address_id' => $address])->assertUnauthorized();

        expect(Address::find($address)->address)->toBe('Damrak 1');
    });

    test('changing an address that does not exist returns 404', function () {
        $api = gapApi(createStoreUser('customer'));

        $api->postJson('/temp/api/v2/user/shipping/update', ['id' => 999999, 'address' => 'x'])->assertNotFound();
        $api->postJson('/temp/api/v2/user/shipping/make_default', ['id' => 999999])->assertNotFound();
    });
});

describe('web checkout address', function () {
    test("a customer cannot ship their cart to another customer's address", function () {
        $me = actingAsStoreUser(createStoreUser('customer'));
        $theirs = addStoreAddress(createStoreUser('customer')->id);
        $cart = orderableCartLine($me, createStoreUser('seller'), 1, cartAttributes: ['address_id' => 0]);

        $this->post('/temp/checkout/delivery_info', ['address_id' => $theirs])->assertForbidden();

        expect($cart->fresh()->address_id)->toBe(0);
    });

    test('a customer can ship their cart to their own address', function () {
        $me = actingAsStoreUser(createStoreUser('customer'));
        $mine = addStoreAddress($me->id);
        $cart = orderableCartLine($me, createStoreUser('seller'), 1, cartAttributes: ['address_id' => 0]);

        expect($this->post('/temp/checkout/delivery_info', ['address_id' => $mine])->getStatusCode())->not->toBeIn([403, 404]);

        expect($cart->fresh()->address_id)->toBe($mine);
    });

    test('a guest and a seller are refused', function (string $callerType) {
        $customer = createStoreUser('customer');
        $cart = orderableCartLine($customer, createStoreUser('seller'), 1, cartAttributes: ['address_id' => 0]);
        if ($callerType !== 'guest') {
            actingAsStoreUser(createStoreUser($callerType));
        }

        expect($this->post('/temp/checkout/delivery_info', ['address_id' => addStoreAddress($customer->id)])->getStatusCode())->toBeIn([302, 404]);

        expect($cart->fresh()->address_id)->toBe(0);
    })->with(['guest', 'seller']);
});

describe('app API refund requests', function () {
    test("a customer cannot ask a refund on another customer's item, or one that is not delivered and paid", function (array $detailState) {
        gapRefundTable();
        $me = createStoreUser('customer');
        $theirs = gapOrderDetail(createStoreUser('customer'));
        $undelivered = gapOrderDetail($me, $detailState);
        $api = gapApi($me);

        $api->postJson('/temp/api/v2/refund-request/send', ['id' => $theirs->id, 'reason' => 'x'])->assertNotFound();
        $api->postJson('/temp/api/v2/refund-request/send', ['id' => $undelivered->id, 'reason' => 'x'])->assertNotFound();
        $api->postJson('/temp/api/v2/refund-request/send', ['id' => 999999, 'reason' => 'x'])->assertNotFound();

        expect(DB::table('refund_requests')->count())->toBe(0);
    })->with([
        'not delivered' => [['delivery_status' => 'pending']],
        'not paid' => [['payment_status' => 'unpaid']],
    ]);

    test('a customer can ask one refund per item, until it is rejected', function () {
        gapRefundTable();
        $me = createStoreUser('customer');
        $detail = gapOrderDetail($me);
        $noise = gapOrderDetail($me);
        $api = gapApi($me);
        $uri = '/temp/api/v2/refund-request/send';

        $api->postJson($uri, ['id' => $detail->id, 'reason' => 'broken'])->assertOk()->assertJsonPath('success', true);
        $api->postJson($uri, ['id' => $detail->id, 'reason' => 'again'])->assertUnprocessable()->assertJsonPath('success', false);

        expect(DB::table('refund_requests')->where('order_detail_id', $detail->id)->count())->toBe(1)
            ->and((float) DB::table('refund_requests')->where('order_detail_id', $detail->id)->value('refund_amount'))->toBe(6.0)
            ->and(DB::table('refund_requests')->where('order_detail_id', $noise->id)->count())->toBe(0);

        DB::table('refund_requests')->where('order_detail_id', $detail->id)->update(['seller_approval' => 2]);
        $api->postJson($uri, ['id' => $detail->id, 'reason' => 'retry'])->assertOk();

        expect(DB::table('refund_requests')->where('order_detail_id', $detail->id)->count())->toBe(2);
    });

    test('a guest is refused', function () {
        gapRefundTable();
        $detail = gapOrderDetail(createStoreUser('customer'));

        gapGuestApi()->postJson('/temp/api/v2/refund-request/send', ['id' => $detail->id, 'reason' => 'x'])->assertUnauthorized();

        expect(DB::table('refund_requests')->count())->toBe(0);
    });
});

describe('app API files', function () {
    test('the file library lists only the caller\'s own uploads, and every upload for an admin', function () {
        $customer = createStoreUser('customer');
        $seller = createStoreUser('seller');
        $own = Upload::create(['file_name' => 'a.png', 'user_id' => $customer->id]);
        $sellers = Upload::create(['file_name' => 'b.png', 'user_id' => $seller->id]);

        $ids = fn ($response) => collect($response->json('data'))->pluck('id')->all();

        expect($ids(gapApi($customer)->getJson('/temp/api/v2/file-all')->assertOk()))->toBe([$own->id])
            ->and($ids(gapApi($seller)->getJson('/temp/api/v2/file-all')->assertOk()))->toBe([$sellers->id])
            ->and($ids(gapApi(createStoreAdmin())->getJson('/temp/api/v2/file-all')->assertOk()))->toEqualCanonicalizing([$own->id, $sellers->id]);
    });

    test('the file library paginates', function () {
        $customer = createStoreUser('customer');
        foreach (range(1, 25) as $n) {
            Upload::create(['file_name' => "f{$n}.png", 'user_id' => $customer->id]);
        }

        gapApi($customer)->getJson('/temp/api/v2/file-all')->assertOk()->assertJsonCount(20, 'data');
    });

    test('a guest is refused the file library', function () {
        gapGuestApi()->getJson('/temp/api/v2/file-all')->assertUnauthorized();
    });

    test("a seller cannot delete another seller's upload, and can delete their own", function () {
        withScratchPublicPath(function (string $publicPath) {
            $owner = createStoreUser('seller');
            file_put_contents("$publicPath/uploads/all/mine.png", 'x');
            file_put_contents("$publicPath/uploads/all/noise.png", 'x');
            $upload = Upload::create(['file_name' => 'uploads/all/mine.png', 'user_id' => $owner->id]);
            $noise = Upload::create(['file_name' => 'uploads/all/noise.png', 'user_id' => createStoreUser('seller')->id]);

            gapApi(createStoreUser('seller'))->getJson("/temp/api/v2/seller/file/delete/{$upload->id}")->assertJsonPath('result', false);

            expect(Upload::find($upload->id))->not->toBeNull()
                ->and(file_exists("$publicPath/uploads/all/mine.png"))->toBeTrue();

            gapApi($owner)->getJson("/temp/api/v2/seller/file/delete/{$upload->id}")->assertJsonPath('result', true);

            expect(Upload::find($upload->id))->toBeNull()
                ->and(file_exists("$publicPath/uploads/all/mine.png"))->toBeFalse()
                ->and(Upload::find($noise->id))->not->toBeNull()
                ->and(file_exists("$publicPath/uploads/all/noise.png"))->toBeTrue();
        });
    });

    test('a customer cannot reach the seller upload delete', function () {
        withScratchPublicPath(function (string $publicPath) {
            $customer = createStoreUser('customer');
            file_put_contents("$publicPath/uploads/all/mine.png", 'x');
            $upload = Upload::create(['file_name' => 'uploads/all/mine.png', 'user_id' => $customer->id]);

            gapApi($customer)->getJson("/temp/api/v2/seller/file/delete/{$upload->id}")->assertNotFound();
            gapGuestApi()->getJson("/temp/api/v2/seller/file/delete/{$upload->id}")->assertUnauthorized();

            expect(Upload::find($upload->id))->not->toBeNull()
                ->and(file_exists("$publicPath/uploads/all/mine.png"))->toBeTrue();
        });
    });
});
