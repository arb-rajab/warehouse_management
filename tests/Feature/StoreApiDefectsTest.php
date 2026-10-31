<?php

use App\Models\Cart;
use App\Models\Order;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Defects the strict-mode sweep of /temp/api/v2 turned up that are not lazy
 * loads: routes with no action behind them, a model that was never written, a
 * query only MySQL could run and a handful of null dereferences.
 */
beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function strictApiAs($test, $user)
{
    return $test->withToken(storeApiToken($user))->withHeader('private-token', 'strict-token');
}

test('the in-house list has the admin products and none of the sellers', function () {
    $store = strictApiStore();

    $names = collect(strictApiAs($this, $store['customer'])->getJson('/temp/api/v2/products/inhouse')->assertOk()->json('data'))->pluck('name')->all();

    // Two in-house priced products; the seller's third one is the noise (the digital ones are in-house too).
    expect($names)->toContain('Prod 1', 'Prod 2')->not->toContain('Prod 3');
});

test('the sub-category routes list a category with its children, like the category route', function (string $path) {
    $store = strictApiStore();
    $category = $store['categories'][0];
    $client = strictApiAs($this, $store['customer']);

    $expected = $client->getJson("/temp/api/v2/products/category/$category")->assertOk()->json('data');

    expect($expected)->not->toBeEmpty()
        ->and($client->getJson("/temp/api/v2/products/$path/$category")->assertOk()->json('data'))->toBe($expected);
})->with(['sub-category', 'sub-sub-category']);

test('no API route points at an action that does not exist', function () {
    $missing = [];
    foreach (Route::getRoutes() as $route) {
        if (str_starts_with($route->uri(), 'temp/api/') && ! $route->isFallback && ! method_exists($route->getControllerClass(), $route->getActionMethod())) {
            $missing[] = $route->uri();
        }
    }

    expect($missing)->toBe([]);
});

test('the driver notifications are reachable under the trips prefix as well', function () {
    $store = strictApiStore();

    $this->withToken(storeApiToken($store['driver']))->getJson('/temp/api/v2/trips/notifications')->assertOk();
});

test('the banners list is empty, not an error, while no banner is configured', function () {
    $store = strictApiStore();

    strictApiAs($this, $store['customer'])->getJson('/temp/api/v2/banners')->assertOk()->assertJsonPath('data', []);
});

test('the home categories list names each pinned category once', function () {
    $store = strictApiStore();
    foreach ($store['categories'] as $category) {
        DB::table('home_categories')->insert(['category_id' => $category, 'status' => 1]);
    }

    $response = strictApiAs($this, $store['customer'])->getJson('/temp/api/v2/home-categories')->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Snacks', 'Drinks']);
});

test('the delivery address of a cart is empty while the cart has none', function () {
    $store = strictApiStore();

    strictApiAs($this, $store['customer'])->getJson('/temp/api/v2/get-home-delivery-address')->assertOk()->assertJsonPath('data', []);

    $address = addStoreAddress($store['customer']->id);
    Cart::where('user_id', $store['customer']->id)->update(['address_id' => $address]);

    expect(strictApiAs($this, $store['customer'])->getJson('/temp/api/v2/get-home-delivery-address')->assertOk()->json('data'))->toHaveCount(1);
});

test('a seller without a package or without a shop has no package info to read', function () {
    $store = strictApiStore();

    strictApiAs($this, $store['seller'])->getJson('/temp/api/v2/seller/package/info')
        ->assertOk()
        ->assertJsonPath('package_name', null)
        ->assertJsonPath('package_img', null);

    DB::table('shops')->where('user_id', $store['seller']->id)->delete();
    app('auth')->forgetGuards();

    strictApiAs($this, $store['seller'])->getJson('/temp/api/v2/seller/package/info')->assertNotFound();
});

test('related classified ads of an unknown ad are a 404', function () {
    $store = strictApiStore();

    strictApiAs($this, $store['customer'])->getJson('/temp/api/v2/classified/related-products/99999')->assertNotFound();
});

test('the purchased digital list holds only the digital lines of the paid orders', function () {
    $store = strictApiStore();
    $digital = $store['products'][3];
    // An order line finds its product by serial.
    foreach ([$digital, $store['products'][4]] as $product) {
        $product->forceFill(['serial' => 'D'.$product->id])->save();
        orderStoreProduct($store['customer'], $product)->forceFill(['product_serial' => $product->serial])->save();
    }

    // The fixture's own paid orders hold three physical lines each, which must not be listed.
    $listed = strictApiAs($this, $store['customer'])->getJson('/temp/api/v2/digital/purchased-list')->assertOk()->json('data');

    expect($listed)->toHaveCount(2);
});

test('the seller sales stat sums the delivered orders of each of the last days', function () {
    $store = strictApiStore();
    backdate($store['orders'][4], now()->subDay()->toDateTimeString());
    $yesterday = now()->subDay()->format('M-d');

    $stat = strictApiAs($this, $store['seller'])->getJson('/temp/api/v2/seller/dashboard/sales-stat')->assertOk()->json();

    expect($stat)->toHaveCount(7)
        ->and((float) collect($stat)->firstWhere('date', $yesterday)['total'])->toBe(5.0)
        ->and(collect($stat)->where('date', '!=', $yesterday)->pluck('total')->unique()->all())->toBe([0]);
});

test('the seller product reviews are listed with their product and reviewer', function () {
    $store = strictApiStore();

    $response = strictApiAs($this, $store['seller'])->getJson('/temp/api/v2/seller/products/reviews')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.product_name'))->toBe('Prod 3');
});

test('an order is placed when only one of the cart brands has a brand offer', function () {
    $store = strictApiStore();
    $user = $store['customer'];
    $address = addStoreAddress($user->id);
    Cart::query()->forceDelete();
    $offer = DB::table('offers')->insertGetId(['title' => 'Brand', 'start_date' => 1, 'end_date' => now()->addYear()->timestamp, 'is_active' => 1, 'offer_type' => 'brand_offer', 'brand_id' => $store['brands'][0]]);
    DB::table('offer_products')->insert([
        ['offer_id' => $offer, 'product_id' => $store['products'][2]->id, 'is_base_product' => 1, 'quantity' => 1, 'discount' => 0, 'discount_type' => null],
        ['offer_id' => $offer, 'product_id' => $store['products'][0]->id, 'is_base_product' => 0, 'quantity' => 1, 'discount' => 1, 'discount_type' => 'percent'],
    ]);
    // Products 1 and 3 carry the second brand, which has no offer, product 2 the first.
    foreach (array_slice($store['products'], 0, 3) as $product) {
        Cart::create(['user_id' => $user->id, 'owner_id' => $product->user_id, 'product_id' => $product->id, 'quantity' => 2, 'variation' => '', 'price' => 2.5, 'address_id' => $address, 'shipping_type' => 'home_delivery']);
    }
    $before = Order::count();

    strictApiAs($this, $user)->postJson('/temp/api/v2/payments/pay/cod', ['payment_type' => 'cash_on_delivery'])->assertOk()->assertJsonPath('result', true);

    expect(Order::count() - $before)->toBe(2);
});
