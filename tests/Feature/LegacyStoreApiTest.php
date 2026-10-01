<?php

use App\Models\Cart;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The store's mobile-app API under /temp/api/v2 (routes/shop-api.php),
 * which nothing had exercised before. Driving it end to end found:
 *
 * - every resource collection came out as {"data":{"data":[...]}}: this app
 *   turns resource wrapping off, and the collections' own `data` key and
 *   with() payload were written for it on (WrapStoreApiResources);
 * - fifteen catalogue endpoints cached their resource objects, which the
 *   cache's serializable_classes allow-list hands back as
 *   __PHP_Incomplete_Class, so each failed from its second request on;
 * - the token lookups outside `auth:sanctum` (the app's session restore, and
 *   every product's per-customer favourite, in-cart and offer fields) read
 *   Sanctum's default token table instead of this app's own;
 * - placing an order failed on the NOT NULL orders.shipping_type and
 *   order_details.tax, as the web checkout did;
 * - purchase history, cart and wishlist deletes and chat messages took any
 *   customer's record by id.
 */
test('an app login returns a token that signs the customer in, and logging out revokes it', function () {
    $customer = createStoreUser('customer', 'app@example.com', 'correct-password');

    $token = $this->postJson('/temp/api/v2/auth/login', ['email' => 'app@example.com', 'password' => 'correct-password', 'login_by' => 'email'])
        ->assertOk()
        ->assertJsonPath('user.id', $customer->id)
        ->json('access_token');

    $this->withToken($token)->getJson('/temp/api/v2/auth/user')
        ->assertOk()
        ->assertJsonPath('id', $customer->id);

    $this->withToken($token)->getJson('/temp/api/v2/auth/logout')->assertOk();
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/temp/api/v2/auth/user')->assertUnauthorized();
});

test('an app login with the wrong password gets no token', function () {
    createStoreUser('customer', 'app@example.com', 'correct-password');

    $this->postJson('/temp/api/v2/auth/login', ['email' => 'app@example.com', 'password' => 'wrong-password', 'login_by' => 'email'])
        ->assertUnauthorized()
        ->assertJsonMissingPath('access_token');
});

test('the app API turns away a request with no token', function () {
    $this->getJson('/temp/api/v2/auth/user')->assertUnauthorized();
    $this->getJson('/temp/api/v2/purchase-history')->assertUnauthorized();
});

test('the app restores a customer from their stored token', function () {
    $customer = createStoreUser('customer');

    $this->postJson('/temp/api/v2/get-user-by-access_token', ['access_token' => storeApiToken($customer)])
        ->assertOk()
        ->assertJsonPath('result', true)
        ->assertJsonPath('id', $customer->id);

    $this->postJson('/temp/api/v2/get-user-by-access_token', ['access_token' => 'not-a-token'])
        ->assertJsonPath('result', false);
});

test('catalogue lists come back in the shape the app reads, from the cache as from the database', function () {
    useSerializingCache();
    seedStoreCurrency();
    $product = createStoreProduct(['todays_deal' => 1]);
    $categoryId = DB::table('categories')->insertGetId(['name' => 'Snacks', 'slug' => 'snacks', 'level' => 0, 'featured' => 1]);
    $this->withToken(storeApiToken(createStoreUser('customer')));

    foreach (['first', 'cached'] as $request) {
        $this->getJson('/temp/api/v2/products/todays-deal')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 200)
            ->assertJsonPath('data.0.id', $product->id);

        $this->getJson('/temp/api/v2/categories/featured')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $categoryId);
    }
});

test("a product list answers each customer's favourites for that customer, cached or not", function () {
    seedStoreCurrency();
    $product = createStoreProduct(['todays_deal' => 1]);
    $fan = createStoreUser('customer');
    Wishlist::create(['user_id' => $fan->id, 'product_id' => $product->id]);

    $this->withToken(storeApiToken($fan))->getJson('/temp/api/v2/products/todays-deal')
        ->assertJsonPath('data.0.is_favorite', true);

    $this->withToken(storeApiToken(createStoreUser('customer')))->getJson('/temp/api/v2/products/todays-deal')
        ->assertJsonPath('data.0.is_favorite', false);
});

test("the store API leaves this app's own resources unwrapped", function () {
    $this->getJson('/temp/api/v2/filter/brands')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(JsonResource::$wrap)->toBeNull();
});

test("a shop's product list links each product's other listings", function () {
    seedStoreCurrency();
    $seller = createStoreUser('seller');
    $shopId = DB::table('shops')->insertGetId(['user_id' => $seller->id, 'name' => 'Seller Shop', 'slug' => 'seller-shop']);
    $product = createStoreProduct(['user_id' => $seller->id, 'added_by' => 'seller']);

    $this->getJson("/temp/api/v2/shops/products/all/{$shopId}")
        ->assertOk()
        ->assertJsonPath('data.0.id', $product->id)
        ->assertJsonPath('data.0.links.top_from_seller', route('products.topFromSeller', $product->id));
});

test('a customer can place a cash-on-delivery order from the app', function () {
    seedStoreCurrency();
    createStoreUser('admin');
    $product = createStoreProduct(['unit_price' => 2.5, 'current_stock' => 50]);
    $customer = createStoreUser('customer');
    $addressId = addStoreAddress($customer->id);
    $this->withToken(storeApiToken($customer));

    $this->postJson('/temp/api/v2/carts/add', ['id' => $product->id, 'quantity' => 2, 'variant' => ''])
        ->assertOk()
        ->assertJsonPath('result', true);
    $this->getJson('/temp/api/v2/cart-summary')->assertOk()->assertJsonPath('grand_total_value', 5);
    $this->postJson('/temp/api/v2/update-address-in-cart', ['address_id' => $addressId])->assertOk();

    $this->postJson('/temp/api/v2/payments/pay/cod', ['payment_type' => 'cash_on_delivery'])
        ->assertOk()
        ->assertJsonPath('result', true);

    $order = Order::sole();

    expect($order->only(['user_id', 'payment_type', 'shipping_type', 'order_from']))->toBe([
        'user_id' => $customer->id,
        'payment_type' => 'cash_on_delivery',
        'shipping_type' => 'home_delivery',
        'order_from' => 'app',
    ])
        ->and(OrderDetail::where('order_id', $order->id)->get(['product_id', 'quantity', 'tax'])->toArray())->toEqual([
            ['product_id' => $product->id, 'quantity' => 2, 'tax' => 0],
        ])
        ->and(Cart::where('user_id', $customer->id)->count())->toBe(0);
});

test("a customer's purchase history opens their own order and not another customer's", function () {
    seedStoreCurrency();
    $customer = createStoreUser('customer');
    $order = createStoreOrder($customer);
    $otherOrder = createStoreOrder(createStoreUser('customer'));
    $this->withToken(storeApiToken($customer));

    $this->getJson("/temp/api/v2/purchase-history-details/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.0.id', $order->id);
    $this->getJson("/temp/api/v2/purchase-history-items/{$order->id}")->assertOk();

    $this->getJson("/temp/api/v2/purchase-history-details/{$otherOrder->id}")->assertForbidden();
    $this->getJson("/temp/api/v2/purchase-history-items/{$otherOrder->id}")->assertForbidden();
});

test('an order a representative placed opens in the purchase history of the customer it was for', function () {
    seedStoreCurrency();
    $customer = createStoreUser('customer');
    $order = createStoreOrder(createStoreUser('customer'), ['by_rep' => 1, 'for_customer' => $customer->id]);

    $this->withToken(storeApiToken($customer))
        ->getJson("/temp/api/v2/purchase-history-details/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.0.id', $order->id);
});

test("a customer can remove their own cart item and not another customer's", function () {
    $product = createStoreProduct();
    $customer = createStoreUser('customer');
    $theirs = Cart::create(['user_id' => createStoreUser('customer')->id, 'product_id' => $product->id, 'quantity' => 3]);
    $mine = Cart::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1]);
    $this->withToken(storeApiToken($customer));

    $this->deleteJson("/temp/api/v2/carts/{$theirs->id}")->assertNotFound();
    $this->deleteJson("/temp/api/v2/carts/{$mine->id}")->assertOk();

    expect(Cart::find($theirs->id))->not->toBeNull()
        ->and(Cart::find($mine->id))->toBeNull();
});

test("a customer can remove their own wishlist item and not another customer's", function () {
    $product = createStoreProduct();
    $customer = createStoreUser('customer');
    $theirs = Wishlist::create(['user_id' => createStoreUser('customer')->id, 'product_id' => $product->id]);
    $mine = Wishlist::create(['user_id' => $customer->id, 'product_id' => $product->id]);
    $this->withToken(storeApiToken($customer));

    $this->deleteJson("/temp/api/v2/wishlists/{$theirs->id}")->assertNotFound();
    $this->deleteJson("/temp/api/v2/wishlists/{$mine->id}")->assertOk();

    expect(Wishlist::whereKey($theirs->id)->exists())->toBeTrue()
        ->and(Wishlist::whereKey($mine->id)->exists())->toBeFalse();
});

test("a customer can read and write their own conversation and not someone else's", function () {
    $customer = createStoreUser('customer');
    $seller = createStoreUser('seller');
    $conversation = Conversation::forceCreate(['sender_id' => $customer->id, 'receiver_id' => $seller->id, 'title' => 'Question']);
    Message::forceCreate(['conversation_id' => $conversation->id, 'user_id' => $customer->id, 'message' => 'Is this in stock?']);

    $this->withToken(storeApiToken(createStoreUser('customer')));
    $this->getJson("/temp/api/v2/chat/messages/{$conversation->id}")->assertForbidden();
    $this->getJson("/temp/api/v2/chat/get-new-messages/{$conversation->id}/0")->assertForbidden();
    $this->postJson('/temp/api/v2/chat/insert-message', ['conversation_id' => $conversation->id, 'message' => 'Buy from me instead'])->assertForbidden();

    expect(Message::where('message', 'Buy from me instead')->exists())->toBeFalse();

    // The sanctum guard keeps the user it resolved for the rest of the test.
    app('auth')->forgetGuards();
    $this->withToken(storeApiToken($customer));
    $this->getJson("/temp/api/v2/chat/messages/{$conversation->id}")
        ->assertOk()
        ->assertJsonPath('data.0.message', 'Is this in stock?');
    $this->postJson('/temp/api/v2/chat/insert-message', ['conversation_id' => $conversation->id, 'message' => 'And in blue?', 'user_id' => $customer->id])
        ->assertOk();

    expect(Message::where('message', 'And in blue?')->exists())->toBeTrue();
});

/**
 * A paid digital product whose file sits at public/`$fileName`. The
 * `uploads` stand-in gets the store's filename columns from the
 * add_upload_metadata_to_uploads migration, and the download names its file from
 * them; they are added here only if a table lacks them.
 */
function digitalProductWithFile(string $fileName): Product
{
    // Added by the metadata migration; guarded for a table that already has them.
    foreach (['file_original_name', 'extension'] as $column) {
        if (! Schema::hasColumn('uploads', $column)) {
            Schema::table('uploads', fn (Blueprint $table) => $table->string($column)->nullable());
        }
    }

    File::ensureDirectoryExists(dirname(public_path($fileName)));
    File::put(public_path($fileName), 'the digital product');
    $uploadId = DB::table('uploads')->insertGetId(['file_name' => $fileName, 'file_original_name' => 'guide', 'extension' => 'txt']);

    return createStoreProduct(['user_id' => createStoreUser('seller')->id, 'digital' => 1, 'file_name' => $uploadId]);
}

test("no one downloads a digital product's file through the app API without a paid order for it", function () {
    $fileName = 'uploads/tests/'.Str::random(8).'.txt';
    $product = digitalProductWithFile($fileName);
    $unpaidBuyer = createStoreUser('customer');
    orderStoreProduct($unpaidBuyer, $product, 'unpaid');
    // A paid order for this product that is someone else's.
    orderStoreProduct(createStoreUser('customer'), $product);

    try {
        foreach ([createStoreUser('customer'), $unpaidBuyer, createStoreUser('seller')] as $caller) {
            app('auth')->forgetGuards();
            $this->withToken(storeApiToken($caller))
                ->getJson("/temp/api/v2/purchased-products/download/{$product->id}")
                ->assertForbidden();
        }
    } finally {
        File::delete(public_path($fileName));
    }
});

test("a customer with a paid order downloads the digital product's file through the app API", function () {
    $fileName = 'uploads/tests/'.Str::random(8).'.txt';
    $product = digitalProductWithFile($fileName);
    $buyer = createStoreUser('customer');
    orderStoreProduct($buyer, $product);

    try {
        $response = $this->withToken(storeApiToken($buyer))
            ->get("/temp/api/v2/purchased-products/download/{$product->id}")
            ->assertOk()
            ->assertDownload('guide.txt');

        expect($response->baseResponse->getFile()->getContent())->toBe('the digital product');
    } finally {
        File::delete(public_path($fileName));
    }

    // The file gone from disk is a 404, where it used to be an empty 200.
    app('auth')->forgetGuards();
    $this->withToken(storeApiToken($buyer))
        ->getJson("/temp/api/v2/purchased-products/download/{$product->id}")
        ->assertNotFound();
});
