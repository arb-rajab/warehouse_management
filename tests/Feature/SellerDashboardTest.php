<?php

use App\Models\Category;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
    $this->travelTo(now()->setDate(2027, 1, 3)->setTime(12, 0));
    $this->seller = createStoreUser('seller');
    actingAsStoreUser($this->seller);
});

test('seller dashboard groups delivered sales by full date across months and excludes unrelated orders', function () {
    createShopFor($this->seller, 0);
    $buyer = createStoreUser();
    $otherSeller = createStoreUser('seller');
    foreach ([
        ['2026-12-30 09:00:00', '10.25', 'delivered', $this->seller->id],
        ['2026-12-30 17:30:00', '2.50', 'delivered', $this->seller->id],
        ['2027-01-02 10:00:00', '9.00', 'delivered', $this->seller->id],
        ['2026-12-27 12:00:00', '4.00', 'delivered', $this->seller->id],
        ['2026-12-27 11:59:59', '500.00', 'delivered', $this->seller->id],
        ['2026-11-30 09:00:00', '600.00', 'delivered', $this->seller->id],
        ['2026-10-30 09:00:00', '700.00', 'delivered', $this->seller->id],
        ['2027-01-02 10:00:00', '800.00', 'pending', $this->seller->id],
        ['2027-01-02 10:00:00', '900.00', 'delivered', $otherSeller->id],
    ] as [$date, $total, $status, $sellerId]) {
        createStoreOrder($buyer, ['seller_id' => $sellerId, 'grand_total' => $total, 'delivery_status' => $status, 'created_at' => $date]);
    }

    $response = $this->get(route('seller.dashboard'))->assertOk()
        ->assertSee('Sales Stat')->assertSee('Top 12 Products');

    expect($response->viewData('last_7_days_sales')->map(fn ($total) => (float) $total)->all())
        ->toBe(['27 Dec' => 4.0, '30 Dec' => 12.75, '02 Jan' => 9.0]);
});

test('seller dashboard renders without orders and with or without shop data', function (bool $hasShop) {
    if ($hasShop) {
        createShopFor($this->seller, 0);
    }

    $this->get(route('seller.dashboard'))->assertOk()
        ->assertSee('Dashboard')->assertSee('Sales Stat')->assertSee('Verify Now')
        ->assertViewHas('last_7_days_sales', fn ($sales) => $sales->isEmpty());
})->with(['shop' => true, 'no shop' => false]);

test('seller dashboard renders populated products categories and paid order lines under strict loading', function () {
    $shopId = createShopFor($this->seller, 0);
    DB::table('shops')->where('id', $shopId)->update(['verification_status' => 1]);
    storeSetting('vendor_system_activation', 1);
    $buyer = createStoreUser();
    $otherSeller = createStoreUser('seller');
    foreach (['Pantry', 'Drinks'] as $name) {
        $category = Category::forceCreate(['name' => $name, 'slug' => strtolower($name), 'parent_id' => 0]);
        $product = createStoreProduct(['name' => $name.' item', 'added_by' => 'seller', 'category_id' => $category->id, 'user_id' => $this->seller->id, 'thumbnail_img' => null]);
        createStoreProduct(['name' => 'Another seller item', 'category_id' => $category->id, 'user_id' => $otherSeller->id, 'thumbnail_img' => null]);
        $order = createStoreOrder($buyer, ['seller_id' => $this->seller->id, 'payment_status' => 'paid']);
        OrderDetail::forceCreate(['order_id' => $order->id, 'product_id' => $product->id, 'seller_id' => $this->seller->id, 'price' => 5, 'tax' => 0, 'quantity' => 3]);
    }
    $unpaid = createStoreOrder($buyer, ['seller_id' => $this->seller->id, 'payment_status' => 'unpaid']);
    OrderDetail::forceCreate(['order_id' => $unpaid->id, 'product_id' => $product->id, 'seller_id' => $this->seller->id, 'price' => 11, 'tax' => 0, 'quantity' => 1]);
    OrderDetail::forceCreate(['order_id' => $order->id, 'product_id' => $product->id, 'seller_id' => $otherSeller->id, 'price' => 23, 'tax' => 0, 'quantity' => 1]);
    $otherCategory = Category::forceCreate(['name' => 'Other category', 'slug' => 'other-category', 'parent_id' => 0]);
    createStoreProduct(['category_id' => $otherCategory->id, 'user_id' => $otherSeller->id]);

    $this->get(route('seller.dashboard'))->assertOk()->assertSee('Pantry item')->assertSee('Drinks item')
        ->assertDontSee('Another seller item')->assertDontSee('Other category')->assertSee('10€');
});

test('seller dashboard refuses a customer', function () {
    actingAsStoreUser(createStoreUser());
    $this->get(route('seller.dashboard'))->assertNotFound();
});

test('seller dashboard refuses a guest', function () {
    auth('store')->logout();
    $this->get(route('seller.dashboard'))->assertNotFound();
});
