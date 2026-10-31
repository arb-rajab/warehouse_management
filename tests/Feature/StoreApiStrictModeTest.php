<?php

use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\StoreUser;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Eloquent's strict mode (local and testing) refuses a lazy load, but only on
 * a result set of two or more models, so the store API tests that build one
 * product, one cart line or one order line never saw it: every list, order and
 * trip of a real customer threw `LazyLoadingViolationException` in local.
 * Everything here is therefore built in twos and threes.
 *
 * The first block pins the values the eager loads feed (prices through taxes,
 * translated names, offered products); the last test drives every GET route
 * of /temp/api/v2 as each kind of caller and fails on any strict-mode
 * exception, so a route added later is covered without a test of its own.
 */
function strictApiCreateLegacyTables(): void
{
    if (! Schema::hasTable('refund_requests')) {
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('seller_id')->nullable();
            $table->integer('order_id');
            $table->integer('order_detail_id');
            $table->text('reason')->nullable();
            $table->text('reject_reason')->nullable();
            $table->integer('seller_approval')->default(0);
            $table->integer('refund_status')->default(0);
            $table->timestamps();
        });
    }
    if (! Schema::hasTable('club_points')) {
        Schema::create('club_points', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('order_id');
            $table->decimal('points', 10, 2)->default(0);
            $table->integer('convert_status')->default(0);
            $table->timestamps();
        });
        Schema::create('club_point_details', function (Blueprint $table) {
            $table->id();
            $table->integer('club_point_id');
            $table->decimal('point', 10, 2)->default(0);
            $table->integer('refunded')->default(0);
            $table->timestamps();
        });
    }
    if (! Schema::hasTable('delivery_histories')) {
        Schema::create('delivery_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('delivery_boy_id');
            $table->integer('order_id');
            $table->string('delivery_status')->nullable();
            $table->string('payment_type')->nullable();
            $table->decimal('earning', 10, 2)->default(0);
            $table->decimal('collection', 10, 2)->default(0);
            $table->integer('created_at')->nullable();
            $table->integer('updated_at')->nullable();
        });
    }
    foreach (['assign_delivery_boy' => 'integer', 'delivery_history_date' => 'string', 'cancel_request' => 'integer'] as $column => $type) {
        if (! Schema::hasColumn('orders', $column)) {
            Schema::table('orders', fn (Blueprint $table) => $table->{$type}($column)->nullable());
        }
    }
}

/**
 * A small store with two or more of everything a store API list reads: three
 * priced and taxed products (two in-house, one a seller's) with a second
 * stock and an Arabic name each, two digital ones, two categories and brands
 * with Arabic names, an offer with two offered products, two flash deals, a
 * wishlist, a cart and an address book of several, an order per delivery
 * status with three lines, refunds and club points, two conversations, a
 * seller coupon and an active trip with an order and a customer checkpoint.
 *
 * @return array<string, mixed>
 */
function strictApiStore(): array
{
    strictApiCreateLegacyTables();
    seedStoreCurrency();
    storeSetting('no_of_decimals', 2);
    storeSetting('vendor_system_activation', 1);
    config(['app.wms_token' => 'strict-token']);

    $admin = createStoreUser('admin');
    $seller = createStoreUser('seller');
    $customer = createStoreUser('customer');
    $deliveryBoy = createStoreUser('delivery_boy');
    $driver = createStoreUser('driver');
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => true])->save();

    DB::table('shops')->insert([
        ['user_id' => $seller->id, 'name' => 'Seller Shop', 'slug' => 'seller-shop', 'verification_status' => 1],
        ['user_id' => $admin->id, 'name' => 'Admin Shop', 'slug' => 'admin-shop', 'verification_status' => 1],
    ]);
    DB::table('sellers')->insert(['user_id' => $seller->id, 'verification_status' => 1]);

    $categories = [];
    foreach (['Snacks', 'Drinks'] as $name) {
        $id = DB::table('categories')->insertGetId(['name' => $name, 'slug' => strtolower($name), 'level' => 0, 'parent_id' => 0, 'featured' => 1, 'top' => 1, 'digital' => 0]);
        DB::table('category_translations')->insert(['category_id' => $id, 'name' => "$name ar", 'lang' => 'sa']);
        $categories[] = $id;
    }
    DB::table('categories')->insert(['name' => 'Chips', 'slug' => 'chips', 'level' => 1, 'parent_id' => $categories[0], 'digital' => 0]);
    storeSetting('home_categories', json_encode($categories));

    $brands = [];
    foreach (['Acme', 'Zed'] as $name) {
        $id = DB::table('brands')->insertGetId(['name' => $name, 'slug' => strtolower($name), 'top' => 1]);
        DB::table('brand_translations')->insert(['brand_id' => $id, 'name' => "$name ar", 'lang' => 'sa']);
        $brands[] = $id;
    }
    $collection = DB::table('product_collections')->insertGetId(['name' => 'Gold', 'serial' => '1', 'amount' => 3]);

    $products = [];
    foreach ([1, 2, 3] as $n) {
        $product = createStoreProduct([
            'name' => "Prod $n", 'serial' => "S$n", 'user_id' => $n === 3 ? $seller->id : $admin->id, 'added_by' => $n === 3 ? 'seller' : 'admin',
            'category_id' => $categories[$n % 2], 'brand_id' => $brands[$n % 2], 'collection_id' => $collection, 'published' => 1, 'approved' => 1,
            'featured' => 1, 'todays_deal' => 1, 'seller_featured' => 1, 'isNewProduct' => 1, 'num_of_sale' => 10 - $n, 'digital' => 0,
            'photos' => '1,2', 'thumbnail_img' => '1', 'variant_product' => 1, 'tax' => 0, 'tax_type' => 'percent',
        ]);
        DB::table('product_stocks')->insert(['product_id' => $product->id, 'variant' => 'Red', 'sku' => "SKU-R-$n", 'price' => 3, 'qty' => 5, 'image' => '1']);
        DB::table('product_taxes')->insert([
            ['product_id' => $product->id, 'tax_id' => 1, 'tax' => 10, 'tax_type' => 'percent'],
            ['product_id' => $product->id, 'tax_id' => 2, 'tax' => 1, 'tax_type' => 'amount'],
        ]);
        DB::table('product_translations')->insert(['product_id' => $product->id, 'name' => "Prod $n ar", 'lang' => 'sa', 'unit' => 'pc', 'description' => 'd']);
        DB::table('reviews')->insert(['product_id' => $product->id, 'user_id' => $customer->id, 'rating' => 4, 'comment' => 'ok']);
        $products[] = $product;
    }
    foreach (['Digital', 'Digital2'] as $name) {
        $products[] = createStoreProduct(['name' => $name, 'digital' => 1, 'published' => 1, 'approved' => 1, 'added_by' => 'admin', 'user_id' => $admin->id, 'category_id' => $categories[0]]);
    }

    $offer = DB::table('offers')->insertGetId(['title' => 'Deal', 'start_date' => 1, 'end_date' => now()->addYear()->timestamp, 'is_active' => 1, 'offer_type' => 'products_offer', 'banner' => '']);
    DB::table('offer_products')->insert([
        ['offer_id' => $offer, 'product_id' => $products[0]->id, 'is_base_product' => 1, 'quantity' => 2, 'discount' => 0, 'discount_type' => null],
        ['offer_id' => $offer, 'product_id' => $products[1]->id, 'is_base_product' => 0, 'quantity' => 1, 'discount' => 10, 'discount_type' => 'percent'],
        ['offer_id' => $offer, 'product_id' => $products[2]->id, 'is_base_product' => 0, 'quantity' => 1, 'discount' => 1, 'discount_type' => 'amount'],
    ]);

    $deals = [];
    foreach ([1, 2] as $n) {
        $deals[] = $deal = DB::table('flash_deals')->insertGetId(['title' => "Flash $n", 'start_date' => 1, 'end_date' => now()->addYear()->timestamp, 'status' => 1, 'featured' => 1, 'slug' => "flash-$n"]);
        foreach ($products as $product) {
            DB::table('flash_deal_products')->insert(['flash_deal_id' => $deal, 'product_id' => $product->id, 'discount' => 5, 'discount_type' => 'percent']);
        }
    }

    foreach ($products as $product) {
        DB::table('wishlists')->insert(['user_id' => $customer->id, 'product_id' => $product->id]);
    }
    foreach (array_slice($products, 0, 3) as $product) {
        Cart::create(['user_id' => $customer->id, 'owner_id' => $product->user_id, 'product_id' => $product->id, 'quantity' => 2, 'variation' => '', 'price' => 2.5]);
    }
    foreach (['A1', 'A2'] as $line) {
        DB::table('addresses')->insert(['user_id' => $customer->id, 'address' => $line, 'country_id' => 1, 'state_id' => 1, 'city_id' => 1]);
    }

    $orders = [];
    foreach (['pending', 'confirmed', 'picked_up', 'on_the_way', 'delivered', 'cancelled'] as $status) {
        $order = createStoreOrder($customer, [
            'seller_id' => $seller->id, 'delivery_status' => $status, 'payment_status' => 'paid', 'assign_delivery_boy' => $deliveryBoy->id,
            'delivery_history_date' => now()->format('Y-m-d H:i:s'), 'cancel_request' => 0,
        ]);
        foreach (array_slice($products, 0, 3) as $product) {
            OrderDetail::forceCreate([
                'order_id' => $order->id, 'seller_id' => $seller->id, 'product_id' => $product->id, 'product_serial' => $product->serial,
                'price' => 5, 'tax' => 0, 'quantity' => 1, 'payment_status' => 'paid', 'delivery_status' => $status,
            ]);
        }
        DB::table('delivery_histories')->insert(['delivery_boy_id' => $deliveryBoy->id, 'order_id' => $order->id, 'delivery_status' => 'delivered', 'payment_type' => 'cash_on_delivery', 'created_at' => time(), 'updated_at' => time()]);
        DB::table('commission_histories')->insert(['order_id' => $order->id, 'order_detail_id' => 1, 'seller_id' => $seller->id, 'admin_commission' => 1, 'seller_earning' => 4, 'created_at' => now(), 'updated_at' => now()]);
        $orders[] = $order;
    }
    foreach ([0, 1] as $i) {
        $line = OrderDetail::where('order_id', $orders[$i]->id)->first();
        DB::table('refund_requests')->insert(['user_id' => $customer->id, 'seller_id' => $seller->id, 'order_id' => $orders[$i]->id, 'order_detail_id' => $line->id, 'reason' => 'r', 'created_at' => now(), 'updated_at' => now()]);
        $points = DB::table('club_points')->insertGetId(['user_id' => $customer->id, 'order_id' => $orders[$i]->id, 'points' => 5, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('club_point_details')->insert([['club_point_id' => $points, 'point' => 2], ['club_point_id' => $points, 'point' => 3]]);
    }

    storeSetting('conversation_system', 1);
    foreach ([$seller->id, $admin->id] as $receiver) {
        $conversation = DB::table('conversations')->insertGetId(['sender_id' => $customer->id, 'receiver_id' => $receiver, 'title' => 't']);
        DB::table('messages')->insert([['conversation_id' => $conversation, 'user_id' => $customer->id, 'message' => 'hi'], ['conversation_id' => $conversation, 'user_id' => $receiver, 'message' => 'yo']]);
    }
    DB::table('coupons')->insert([
        'user_id' => $seller->id, 'type' => 'product_base', 'code' => 'C1', 'discount' => 5, 'discount_type' => 'percent', 'start_date' => 1, 'end_date' => time() + 1000,
        'details' => json_encode([['product_id' => $products[2]->id], ['product_id' => $products[0]->id]]),
    ]);

    $truck = DB::table('trucks')->insertGetId(['name' => 'Truck', 'license_plate' => 'X', 'max_pallets_number' => '5', 'photos' => '1']);
    foreach ([true, false] as $active) {
        $trip = DB::table('trips')->insertGetId(['code' => 'T'.(int) $active, 'driver_id' => $driver->id, 'truck_id' => $truck, 'active' => $active, 'status' => $active ? 'started' : 'closed', 'due_date' => now()->toDateString()]);
        foreach ([$orders[0], $orders[1]] as $position => $order) {
            DB::table('checkpoints')->insert(['trip_id' => $trip, 'relationable_id' => $order->id, 'relationable_type' => Order::class, 'type' => 'delivery', 'status' => 'pending', 'sort_order_in_trip' => $position]);
        }
        DB::table('checkpoints')->insert(['trip_id' => $trip, 'relationable_id' => $customer->id, 'relationable_type' => StoreUser::class, 'type' => 'other', 'status' => 'pending', 'sort_order_in_trip' => 2]);
    }

    return compact('admin', 'seller', 'customer', 'deliveryBoy', 'driver', 'rep', 'products', 'categories', 'brands', 'orders', 'deals');
}

// 3 products at 2.50 plus 10% and 1.00 of tax: the price an eager-loaded
// taxes relation produces, which a missing one would turn into 2.50.
const STRICT_API_TAXED_PRICE = 3.75;

dataset('store catalogue lists', [
    'all products' => ['products', 5],
    'new products' => ['products/new_products', 3],
    "today's deal" => ['products/todays-deal', 3],
    'featured' => ['products/featured', 3],
    'best sellers' => ['products/best-seller', 3],
    'digital' => ['products/digital', 2],
    'search' => ['products/search', 3],
    'a category' => ['products/category/{category}', 2],
    'a brand' => ['products/brand/{brand}', 2],
    'a seller' => ['products/seller/{sellerShop}', 1],
    'related products' => ['products/related/{product}', 1],
    'more from the seller' => ['products/top-from-seller/{product}', 2],
    'flash deal products' => ['flash-deal-products/{deal}', 5],
    'a shop: all products' => ['shops/products/all/{adminShop}', 4],
    'a shop: top selling' => ['shops/products/top/{adminShop}', 4],
    'a shop: featured' => ['shops/products/featured/{adminShop}', 2],
    'a shop: new' => ['shops/products/new/{adminShop}', 4],
]);

test('every store catalogue list builds for two or more products, from the cache as from the database', function (string $path, int $count) {
    useSerializingCache();
    $store = strictApiStore();
    $uri = strtr($path, [
        '{category}' => $store['categories'][1], '{brand}' => $store['brands'][1], '{product}' => $store['products'][0]->id,
        '{deal}' => $store['deals'][0], '{adminShop}' => DB::table('shops')->where('user_id', $store['admin']->id)->value('id'),
        '{sellerShop}' => DB::table('shops')->where('user_id', $store['seller']->id)->value('id'),
    ]);
    $this->withToken(storeApiToken($store['customer']))->withHeader('App-Language', 'sa');

    foreach (['first', 'cached'] as $request) {
        $response = $this->getJson("/temp/api/v2/$uri")->assertOk();

        expect($response->json('data'))->toHaveCount($count);
        $product = collect($response->json('data'))->firstWhere('id', $store['products'][1]->id);

        // Lists that do not hold the second product, or (a shop's full list) are priced differently, skip the value check.
        if (! str_starts_with($path, 'shops/products/all') && $product !== null) {
            expect($product['name'])->toBe('Prod 2 ar')
                ->and($product['calculable_price'])->toBe(STRICT_API_TAXED_PRICE);
        }
    }
})->with('store catalogue lists');

test("a product's offer lists both offered products with their taxed, discounted prices", function () {
    $store = strictApiStore();

    $offer = $this->withToken(storeApiToken($store['customer']))->withHeader('App-Language', 'sa')
        ->getJson('/temp/api/v2/products')->assertOk()
        ->collect('data')->firstWhere('id', $store['products'][0]->id)['offers'];

    expect($offer['title'])->toBe('Deal')
        ->and($offer['quantity_required'])->toBe(2)
        ->and(collect($offer['offered_products'])->pluck('product_name')->all())->toBe(['Prod 2 ar', 'Prod 3 ar'])
        // 2.50 less 10% is 2.25, then 10% and 1.00 of tax is 3.475; 2.50 less 1.00 is 1.50, then the same tax is 2.65.
        ->and(collect($offer['offered_products'])->pluck('discounted_price')->map(fn ($price) => round($price, 2))->all())->toBe([3.48, 2.65]);
});

test("a product's offer sends its banner, from the list and from the detail", function () {
    $store = strictApiStore();
    $productId = $store['products'][0]->id;
    $upload = DB::table('uploads')->insertGetId(['file_name' => 'uploads/all/banner.png']);
    $request = $this->withToken(storeApiToken($store['customer']))->withHeader('App-Language', 'sa');

    $without = $request->getJson('/temp/api/v2/products')->assertOk()->collect('data')->firstWhere('id', $productId)['offers'];
    DB::table('offers')->update(['banner' => (string) $upload]);
    $list = $request->getJson('/temp/api/v2/products')->assertOk()->collect('data')->firstWhere('id', $productId)['offers'];
    $detail = $request->getJson("/temp/api/v2/products/$productId")->assertOk()->json('data.0.offers');

    expect($without)->not->toHaveKey('banner')
        ->and($list['banner'])->toBe(uploaded_asset($upload))->toContain('banner.png')
        ->and($detail['banner'])->toBe($list['banner']);
});

test('category, brand and sub-category lists carry their Arabic names, from the cache as from the database', function () {
    useSerializingCache();
    $store = strictApiStore();
    $this->withHeader('App-Language', 'sa');

    foreach (['first', 'cached'] as $request) {
        foreach (['categories/featured', 'categories/home', 'categories/top', 'filter/categories'] as $path) {
            expect($this->getJson("/temp/api/v2/$path")->assertOk()->json('data.*.name'))->toContain('Snacks ar', 'Drinks ar');
        }
        expect($this->getJson('/temp/api/v2/categories')->assertOk()->json('data.*.name'))->toContain('Snacks ar', 'Drinks ar')
            ->and($this->getJson("/temp/api/v2/sub-categories/{$store['categories'][0]}")->assertOk()->json('data'))->toHaveCount(1);
        foreach (['brands', 'brands/top', 'filter/brands'] as $path) {
            expect($this->getJson("/temp/api/v2/$path")->assertOk()->json('data.*.name'))->toContain('Acme ar', 'Zed ar');
        }
    }
});

test('flash deals list their first products with taxed prices', function () {
    $store = strictApiStore();

    $deals = $this->getJson('/temp/api/v2/flash-deals')->assertOk()->json('data');

    expect($deals)->toHaveCount(2)
        ->and($deals[0]['products']['data'])->toHaveCount(5)
        ->and(collect($deals[0]['products']['data'])->firstWhere('id', $store['products'][1]->id)['calculable_price'])->toBe(STRICT_API_TAXED_PRICE);
});

test("a customer's wishlist, addresses, purchase history and chats list every record", function () {
    $store = strictApiStore();
    $this->withToken(storeApiToken($store['customer']));

    expect($this->getJson('/temp/api/v2/wishlists')->assertOk()->json('data'))->toHaveCount(5)
        ->and($this->getJson('/temp/api/v2/user/shipping/address')->assertOk()->json('data.*.address'))->toBe(['A1', 'A2'])
        ->and($this->getJson("/temp/api/v2/purchase-history-items/{$store['orders'][0]->id}")->assertOk()->json('data'))->toHaveCount(3)
        ->and($this->getJson('/temp/api/v2/purchase-history')->assertOk()->json('data'))->toHaveCount(6)
        ->and($this->getJson('/temp/api/v2/chat/conversations')->assertOk()->json('data'))->toHaveCount(2)
        ->and($this->getJson('/temp/api/v2/refund-request/get-list')->assertOk()->json('data'))->toHaveCount(2)
        ->and($this->getJson('/temp/api/v2/clubpoint/get-list')->assertOk()->json('data.*.convertible_club_point'))->toBe([5, 5]);

    $conversation = DB::table('conversations')->value('id');
    expect($this->getJson("/temp/api/v2/chat/messages/$conversation")->assertOk()->json('data'))->toHaveCount(2);
});

test("a delivery boy's lists, earnings and order items carry every order", function () {
    $store = strictApiStore();
    $id = $store['deliveryBoy']->id;
    $this->withToken(storeApiToken($store['deliveryBoy']));

    foreach (['assigned' => 2, 'picked_up' => 1, 'on_the_way' => 1, 'completed' => 1, 'cancelled' => 1] as $list => $count) {
        expect($this->getJson("/temp/api/v2/delivery-boy/deliveries/$list/$id")->assertOk()->json('data'))->toHaveCount($count);
    }
    expect($this->getJson("/temp/api/v2/delivery-boy/earning/$id")->assertOk()->json('data'))->toHaveCount(6)
        ->and($this->getJson("/temp/api/v2/delivery-boy/purchase-history-items/{$store['orders'][0]->id}")->assertOk()->json('data'))->toHaveCount(3);
});

test("a seller's orders, coupons, products, commissions and conversations carry every record", function () {
    $store = strictApiStore();
    $this->withToken(storeApiToken($store['seller']));

    expect($this->getJson("/temp/api/v2/seller/orders/details/{$store['orders'][0]->id}")->assertOk()->json('data.0.order_items'))->toHaveCount(3)
        ->and($this->getJson('/temp/api/v2/seller/coupon/all')->assertOk()->json('data.0.details'))->toBeString()
        ->and(json_decode($this->getJson('/temp/api/v2/seller/coupon/all')->json('data.0.details'), true)['data'])->toHaveCount(2)
        ->and($this->getJson('/temp/api/v2/seller/commission-list')->assertOk()->json('data'))->toHaveCount(6)
        ->and($this->getJson('/temp/api/v2/seller/dashboard/top-12-product')->assertOk()->json('data'))->toHaveCount(1)
        ->and($this->getJson('/temp/api/v2/seller/conversations')->assertOk()->json('data'))->toHaveCount(1);

    $categories = $this->getJson('/temp/api/v2/seller/dashboard/category-wise-products')->assertOk()->json();
    expect(collect($categories)->pluck('cnt_product', 'name')->all())->toBe(['Drinks' => 1])
        ->and($this->getJson('/temp/api/v2/seller/refunds')->assertOk()->json('data'))->toHaveCount(2);
});

test("a driver's active trip and history carry every checkpoint", function () {
    $store = strictApiStore();
    $this->withToken(storeApiToken($store['driver']));

    $active = $this->getJson('/temp/api/v2/trips/get-active-trip')->assertOk()->json('data');
    $history = $this->getJson('/temp/api/v2/trips/get-trips-history')->assertOk()->json('data');

    expect($active['checkpoints'])->toHaveCount(3)
        ->and(collect($active['checkpoints'])->pluck('order.order_details')->filter()->first())->toHaveCount(3)
        ->and($history)->toHaveCount(1)
        ->and($history[0]['checkpoints'])->toHaveCount(3);
});

dataset('placing customers', ['a customer' => ['customer'], 'a representative' => ['rep']]);

test('an order of several cart lines is placed, brand offers included', function (string $who) {
    $store = strictApiStore();
    $user = $store[$who];
    $address = addStoreAddress($user->id);
    Cart::query()->forceDelete();
    foreach (DB::table('brands')->pluck('id') as $brandId) {
        $offer = DB::table('offers')->insertGetId(['title' => 'Brand', 'start_date' => 1, 'end_date' => now()->addYear()->timestamp, 'is_active' => 1, 'offer_type' => 'brand_offer', 'brand_id' => $brandId]);
        DB::table('offer_products')->insert([
            ['offer_id' => $offer, 'product_id' => $store['products'][2]->id, 'is_base_product' => 1, 'quantity' => 1, 'discount' => 0, 'discount_type' => null],
            ['offer_id' => $offer, 'product_id' => $store['products'][0]->id, 'is_base_product' => 0, 'quantity' => 1, 'discount' => 1, 'discount_type' => 'percent'],
            ['offer_id' => $offer, 'product_id' => $store['products'][1]->id, 'is_base_product' => 0, 'quantity' => 1, 'discount' => 1, 'discount_type' => 'percent'],
        ]);
    }
    foreach (array_slice($store['products'], 0, 3) as $product) {
        Cart::create([
            'user_id' => $user->id, 'owner_id' => $product->user_id, 'product_id' => $product->id, 'quantity' => 2, 'variation' => '', 'price' => 2.5,
            'address_id' => $address, 'shipping_type' => 'home_delivery', 'for_customer' => $who === 'rep' ? $store['customer']->id : null,
        ]);
    }
    $before = Order::count();
    $this->withToken(storeApiToken($user));

    $this->getJson('/temp/api/v2/payment-types')->assertOk();
    $this->postJson('/temp/api/v2/carts')->assertOk();
    $this->postJson('/temp/api/v2/payments/pay/cod', ['payment_type' => 'cash_on_delivery'])
        ->assertOk()
        ->assertJsonPath('result', true);

    // One order per seller (the in-house products and the seller's), and the cart emptied.
    expect(Order::count() - $before)->toBe(2)
        ->and(Cart::where('user_id', $user->id)->count())->toBe(0);
})->with('placing customers');

test('the price helpers read the taxes and stocks of a product from a list that did not load them', function () {
    $store = strictApiStore();
    $products = Product::query()->whereIn('id', collect($store['products'])->take(3)->pluck('id'))->orderBy('id')->get();
    $range = fn (string $range): array => array_map(fn (string $price): float => round((float) $price, 2), explode(' - ', $range));

    // A list of three with nothing eager-loaded: the case the guard refuses.
    expect($products)->toHaveCount(3)
        ->and($products->first()->relationLoaded('taxes'))->toBeFalse();

    foreach ($products as $product) {
        // Unit price 2.50 (a Red stock at 3.00 lifts the top of the range), plus 10% and 1.00 of tax.
        expect((float) home_base_price($product, false))->toBe(STRICT_API_TAXED_PRICE)
            ->and((float) home_discounted_base_price($product, false))->toBe(STRICT_API_TAXED_PRICE)
            ->and($range(home_price($product, false)))->toBe([STRICT_API_TAXED_PRICE, 4.3])
            ->and($range(home_discounted_price($product, false)))->toBe([STRICT_API_TAXED_PRICE, 4.3])
            ->and($product->relationLoaded('taxes'))->toBeTrue()
            ->and($product->relationLoaded('stocks'))->toBeTrue();
    }
});

test('remember_models loads the relations it is given onto cached models, on a hit as on a miss', function () {
    useSerializingCache();
    $store = strictApiStore();

    foreach (['miss', 'hit'] as $read) {
        $categories = remember_models(Category::class, 'strict-api-categories', 60, fn () => Category::query()->where('level', 0)->get(), ['category_translations']);

        expect($categories)->toHaveCount(2)
            ->and($categories->every(fn ($category) => $category->relationLoaded('category_translations')))->toBeTrue()
            ->and($categories->map->getTranslation('name')->all())->toBe(['Snacks', 'Drinks']);
    }
});

test('a representative with an empty cart has no current customer, and one with a cart has the customer it is for', function () {
    $store = strictApiStore();
    $rep = $store['rep'];

    expect($rep->current_customer)->toBeNull();

    Cart::create(['user_id' => $rep->id, 'owner_id' => 1, 'product_id' => $store['products'][0]->id, 'quantity' => 1, 'for_customer' => $store['customer']->id]);

    expect($rep->fresh()->current_customer?->id)->toBe($store['customer']->id);
});

test('no GET route of the store API trips Eloquent strict mode for any kind of caller', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->withoutExceptionHandling();
    $store = strictApiStore();
    $callers = ['customer', 'seller', 'deliveryBoy', 'driver', 'rep', 'admin'];
    $skipped = '#logout|account-deletion|/delete/|wishlists-remove|duplicate|/begin|sslcommerz|bkash|nagad|iyzico#';
    $violations = [];

    foreach ($callers as $caller) {
        $token = storeApiToken($store[$caller]);

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'temp/api/v2/') || ! in_array('GET', $route->methods(), true) || preg_match($skipped, $uri)) {
                continue;
            }
            $id = str_contains($uri, 'delivery-boy/') ? $store['deliveryBoy']->id : 1;
            app('auth')->forgetGuards();

            try {
                $this->withToken($token)->withHeader('private-token', 'strict-token')->getJson('/'.preg_replace('/\{[^}]+\}/', (string) $id, $uri));
            } catch (LazyLoadingViolationException|MissingAttributeException $exception) {
                $violations[] = "$caller GET /$uri: ".$exception->getMessage();
            } catch (Throwable) {
                // Anything else (a missing addon table, a handler not written) is a different defect.
            }
        }
    }

    expect($violations)->toBe([]);
});
