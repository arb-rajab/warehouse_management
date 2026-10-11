<?php

use App\Models\Category;
use App\Models\CommissionHistory;
use App\Models\Product;
use App\Models\Search;
use App\Models\Shop;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => seedStoreCurrency());

dataset('store reports', [
    'inhouse' => ['in_house_sale_report', 'in_house_product_sale_report', 'InHouseSales'],
    'sellers' => ['seller_sale_report', 'seller_products_sale_report', 'SellerSales'],
    'stock' => ['stock_report', 'products_stock_report', 'Stock'],
    'wishlist' => ['wish_report', 'product_wishlist_report', 'Wishlist'],
    'searches' => ['user_search_report', 'user_search_report', 'Searches'],
    'commission' => ['commission-log', 'commission_history_report', 'CommissionHistory'],
    'wallet' => ['wallet-history', 'wallet_transaction_report', 'WalletHistory'],
]);

/** @return array<int, int> */
function seedStoreReportRows(string $uri, int $count): array
{
    return array_map(function (int $number) use ($uri): int {
        return match ($uri) {
            'seller_sale_report' => Shop::forceCreate(['user_id' => createStoreUser('seller')->id, 'name' => 'Shop '.$number])->id,
            'user_search_report' => Search::forceCreate(['query' => 'search '.$number, 'count' => $number])->id,
            'commission-log' => CommissionHistory::forceCreate(['order_id' => 99999, 'order_detail_id' => 99999, 'seller_id' => 99999, 'admin_commission' => 1, 'seller_earning' => 2])->id,
            'wallet-history' => Wallet::forceCreate(['user_id' => 99999, 'amount' => 3, 'payment_method' => 'cash'])->id,
            default => tap(Product::factory()->create()->forceFill(['added_by' => 'admin', 'num_of_sale' => $number]), fn (Product $p) => $p->save())->id,
        };
    }, range(1, $count));
}

test('each report renders its Vue page', function (string $uri, string $permission, string $page) {
    actingAsStoreUser(createStoreStaff($permission));
    $this->get('/temp/admin/'.$uri)->assertOk()->assertInertia(fn (Assert $assert) => $assert->component('Store/Admin/Reports/'.$page));
})->with('store reports');

test('every report refuses unauthorized callers', function (string $uri, string $permission, string $page, string $caller) {
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get('/temp/admin/'.$uri), $caller);
})->with('store reports')->with('refused store admin callers');

test('every report paginates and retains query parameters', function (string $uri, string $permission) {
    actingAsStoreUser(createStoreStaff($permission));
    seedStoreReportRows($uri, 23);
    $first = $this->get('/temp/admin/'.$uri)->assertOk()->inertiaProps('rows');
    $second = $this->get('/temp/admin/'.$uri.'?page=2')->assertOk()->inertiaProps('rows');
    expect($first['data'])->toHaveCount(20)->and($first['meta']['total'])->toBe(23)->and($second['data'])->toHaveCount(3)
        ->and(array_intersect(array_column($first['data'], 'id'), array_column($second['data'], 'id')))->toBe([]);
    $custom = $this->get('/temp/admin/'.$uri.'?per_page=10')->assertOk()->inertiaProps('rows');
    expect($custom['data'])->toHaveCount(10)->and($custom['meta']['per_page'])->toBe(10)->and($custom['links']['next'])->toContain('per_page=10');
    $this->getJson('/temp/admin/'.$uri.'?per_page=500')->assertUnprocessable()->assertJsonValidationErrors('per_page');
})->with('store reports');

test('product reports translate names, scope categories and compute only their metric', function (string $uri, string $permission, int $value) {
    actingAsStoreUser(createStoreStaff($permission));
    $category = Category::create(['name' => 'Fruit']);
    $other = Category::create(['name' => 'Noise']);
    $product = Product::factory()->create(['name' => 'Pear'])->forceFill(['category_id' => $category->id, 'added_by' => 'admin', 'num_of_sale' => 7]);
    $product->save();
    DB::table('product_translations')->insert(['product_id' => $product->id, 'lang' => 'en', 'name' => 'Translated pear']);
    DB::table('category_translations')->insert(['category_id' => $category->id, 'lang' => 'en', 'name' => 'Translated fruit']);
    $noise = Product::factory()->create()->forceFill(['category_id' => $other->id, 'num_of_sale' => 999]);
    $noise->save();
    DB::table('product_stocks')->insert([['product_id' => $product->id, 'qty' => 3], ['product_id' => $product->id, 'qty' => 8], ['product_id' => $noise->id, 'qty' => 999]]);
    DB::table('wishlists')->insert([['product_id' => $product->id, 'user_id' => 1], ['product_id' => $product->id, 'user_id' => 2], ['product_id' => $noise->id, 'user_id' => 1]]);
    $this->get('/temp/admin/'.$uri.'?category_id='.$category->id)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('rows.data', 1)->has('rows.data.0', fn (Assert $row) => $row->where('id', $product->id)->where('name', 'Translated pear')->where('value', $value))
        ->where('filters', ['category_ids' => [(string) $category->id], 'per_page' => 20])
        ->where('options', [['value' => (string) $category->id, 'label' => 'Translated fruit'], ['value' => (string) $other->id, 'label' => 'Noise']]));
    expect(array_column($this->get('/temp/admin/'.$uri.'?'.http_build_query(['category_ids' => [$category->id, $other->id]]))->assertOk()->inertiaProps('rows')['data'], 'id'))->toContain($product->id, $noise->id);
})->with([
    ['in_house_sale_report', 'in_house_product_sale_report', 7],
    ['stock_report', 'products_stock_report', 11],
    ['wish_report', 'product_wishlist_report', 2],
]);

test('inhouse sales exclude seller products and sort by sales', function () {
    actingAsStoreUser(createStoreStaff('in_house_product_sale_report'));
    $low = Product::factory()->create()->forceFill(['added_by' => 'admin', 'num_of_sale' => 2]);
    $low->save();
    $high = Product::factory()->create()->forceFill(['added_by' => 'admin', 'num_of_sale' => 5]);
    $high->save();
    $seller = Product::factory()->create()->forceFill(['added_by' => 'seller', 'num_of_sale' => 999]);
    $seller->save();
    expect(array_column($this->get('/temp/admin/in_house_sale_report')->assertOk()->inertiaProps('rows')['data'], 'id'))->toBe([$high->id, $low->id]);
});

test('seller sales use the shop name, isolate aggregate amounts, and accept unapproved status zero', function () {
    actingAsStoreUser(createStoreStaff('seller_products_sale_report'));
    $seller = createStoreUser('seller');
    $seller->forceFill(['name' => 'Seller'])->save();
    $noise = createStoreUser('seller');
    $shop = Shop::forceCreate(['user_id' => $seller->id, 'name' => 'Pear shop', 'verification_status' => 0]);
    Shop::forceCreate(['user_id' => $noise->id, 'name' => 'Other shop', 'verification_status' => 1]);
    foreach ([4, 6] as $sales) {
        Product::factory()->create()->forceFill(['user_id' => $seller->id, 'num_of_sale' => $sales])->save();
    }
    Product::factory()->create()->forceFill(['user_id' => $noise->id, 'num_of_sale' => 999])->save();
    DB::table('order_details')->insert([['seller_id' => $seller->id, 'order_id' => 1, 'product_id' => 1, 'price' => 12.50, 'quantity' => 3], ['seller_id' => $seller->id, 'order_id' => 2, 'product_id' => 1, 'price' => 20, 'quantity' => 4], ['seller_id' => $noise->id, 'order_id' => 3, 'product_id' => 1, 'price' => 999, 'quantity' => 5]]);
    $this->get('/temp/admin/seller_sale_report?verification_status=0')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('rows.data', 1)->has('rows.data.0', fn (Assert $row) => $row->where('id', $shop->id)->where('seller_name', 'Seller')->where('shop_name', 'Pear shop')->where('num_of_sale', 10)->where('order_amount', single_price(32.5)))
        ->where('filters', ['verification_statuses' => ['0'], 'per_page' => 20]));
    $this->get('/temp/admin/seller_sale_report')->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows.data', 2));
});

test('search report returns only its complete row shape in count order', function () {
    actingAsStoreUser(createStoreStaff('user_search_report'));
    $low = Search::forceCreate(['query' => 'pear', 'count' => 2]);
    $high = Search::forceCreate(['query' => 'apple', 'count' => 20]);
    $this->get('/temp/admin/user_search_report')->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows.data', 2)
        ->has('rows.data.0', fn (Assert $row) => $row->where('id', $high->id)->where('query', 'apple')->where('count', 20))->where('rows.data.1.id', $low->id));
});

test('history reports include both whole-day boundaries and exclude unrelated account and adjacent dates', function (string $uri, string $permission, string $model, string $accountKey) {
    actingAsStoreUser(createStoreStaff($permission));
    $owner = createStoreUser('seller');
    $owner->forceFill(['name' => 'Owner'])->save();
    $noise = createStoreUser('seller');
    $attributes = $model === Wallet::class ? ['amount' => 5.50, 'payment_method' => 'bank_transfer'] : ['order_id' => 99999, 'order_detail_id' => 1, 'admin_commission' => 1.25, 'seller_earning' => 8.75];
    $first = $model::forceCreate([$accountKey => $owner->id, ...$attributes, 'created_at' => '2026-01-01 00:00:00']);
    $last = $model::forceCreate([$accountKey => $owner->id, ...$attributes, 'created_at' => '2026-01-02 23:59:59']);
    foreach ([[$owner->id, '2025-12-31 23:59:59'], [$owner->id, '2026-01-03 00:00:00'], [$noise->id, '2026-01-02 12:00:00']] as [$id, $date]) {
        $model::forceCreate([$accountKey => $id, ...$attributes, 'created_at' => $date]);
    }
    $query = http_build_query([$accountKey => $owner->id, 'date_range' => '2026-01-01 / 2026-01-02', 'per_page' => 10]);
    $response = $this->get('/temp/admin/'.$uri.'?'.$query)->assertOk();
    expect(array_column($response->inertiaProps('rows')['data'], 'id'))->toBe([$last->id, $first->id]);
    $response->assertInertia(fn (Assert $page) => $page->has('rows.data.0', function (Assert $row) use ($model, $last) {
        $row->where('id', $last->id)->where('created_at', '2026-01-02T23:59:59+00:00');
        if ($model === Wallet::class) {
            $row->where('user_name', 'Owner')->where('amount', single_price(5.5))->where('payment_method', 'Bank transfer');
        } else {
            $row->where('order_code', null)->where('admin_commission', '1.25')->where('seller_earning', '8.75');
        }
    }));
    $selected = $model === Wallet::class ? 'user_ids' : 'seller_ids';
    expect($response->inertiaProps('filters'))->toBe([$selected => [(string) $owner->id], 'date_from' => '2026-01-01', 'date_to' => '2026-01-02', 'created_within_days' => null, 'per_page' => 10]);
})->with([
    ['commission-log', 'commission_history_report', CommissionHistory::class, 'seller_id'],
    ['wallet-history', 'wallet_transaction_report', Wallet::class, 'user_id'],
]);

test('history reports validate malformed, impossible and reversed ranges', function (string $uri, string $range) {
    actingAsStoreUser(createStoreAdmin());
    $this->getJson('/temp/admin/'.$uri.'?'.http_build_query(['date_range' => $range]))->assertUnprocessable()->assertJsonValidationErrors('date_range');
})->with(['commission-log', 'wallet-history'])->with(['yesterday', '2026-02-01 / 2026-01-01', '2026-02-31 / 2026-03-01']);

test('canonical date filters reject conflicts and invalid references', function (array $filter, string $key) {
    actingAsStoreUser(createStoreStaff('commission_history_report'));
    $this->getJson('/temp/admin/commission-log?'.http_build_query($filter))->assertUnprocessable()->assertJsonValidationErrors($key);
})->with([
    [['date_from' => '2026-02-31'], 'date_from'],
    [['date_from' => '2026-02-02', 'date_to' => '2026-02-01'], 'date_to'],
    [['date_from' => '2026-02-01', 'created_within_days' => 2], 'created_within_days'],
    [['created_within_days' => 0], 'created_within_days'],
    [['seller_id' => 'abc'], 'seller_id'],
    [['seller_ids' => ['abc']], 'seller_ids.0'],
]);

test('wallet orphan accounts and null payment methods stay renderable', function () {
    actingAsStoreUser(createStoreStaff('wallet_transaction_report'));
    $wallet = Wallet::forceCreate(['user_id' => 999999, 'amount' => 3, 'payment_method' => null]);
    $this->get('/temp/admin/wallet-history')->assertOk()->assertInertia(fn (Assert $page) => $page->where('rows.data.0.id', $wallet->id)->where('rows.data.0.user_name', null)->where('rows.data.0.payment_method', null)->where('options', []));
});

test('rolling history windows include the exact cutoff and exclude older rows', function (string $uri, string $permission, string $model, string $key) {
    actingAsStoreUser(createStoreStaff($permission));
    $this->travelTo(now()->setDate(2026, 2, 10)->setTime(12, 0));
    $attributes = $model === Wallet::class ? ['amount' => 4] : ['order_id' => 999999, 'order_detail_id' => 1, 'admin_commission' => 1, 'seller_earning' => 3];
    $included = $model::forceCreate([$key => 1, ...$attributes, 'created_at' => '2026-02-08 12:00:00']);
    $model::forceCreate([$key => 1, ...$attributes, 'created_at' => '2026-02-08 11:59:59']);
    expect(array_column($this->get('/temp/admin/'.$uri.'?created_within_days=2')->assertOk()->inertiaProps('rows')['data'], 'id'))->toBe([$included->id]);
})->with([
    ['commission-log', 'commission_history_report', CommissionHistory::class, 'seller_id'],
    ['wallet-history', 'wallet_transaction_report', Wallet::class, 'user_id'],
]);

test('commission orders and filter options use only the required shape', function () {
    actingAsStoreUser(createStoreStaff('commission_history_report'));
    $seller = createStoreUser('seller');
    $seller->forceFill(['name' => 'Seller'])->save();
    createStoreUser('customer');
    $order = createStoreOrder(createStoreUser('customer'), ['code' => 'ORDER-1']);
    $commission = CommissionHistory::forceCreate(['order_id' => $order->id, 'order_detail_id' => 1, 'seller_id' => $seller->id, 'admin_commission' => 2.5, 'seller_earning' => 10, 'created_at' => '2026-01-10 12:00:00']);
    $this->get('/temp/admin/commission-log')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('rows.data.0', fn (Assert $row) => $row->where('id', $commission->id)->where('order_code', 'ORDER-1')->where('admin_commission', '2.5')->where('seller_earning', '10')->where('created_at', '2026-01-10T12:00:00+00:00'))
        ->where('options', [['value' => (string) $seller->id, 'label' => 'Seller']]));
});

test('stock and wishlist default to zero without loading per-row collections', function (string $uri, string $permission, string $table) {
    actingAsStoreUser(createStoreStaff($permission));
    $ids = seedStoreReportRows($uri, 3);
    DB::enableQueryLog();
    $rows = $this->get('/temp/admin/'.$uri)->assertOk()->inertiaProps('rows')['data'];
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();
    expect(array_column($rows, 'value'))->toBe([0, 0, 0])->and(array_column($rows, 'id'))->toBe(array_reverse($ids));
    expect($queries->filter(fn (string $sql): bool => str_starts_with($sql, 'select * from "'.$table.'"')))->toHaveCount(0);
})->with([
    ['stock_report', 'products_stock_report', 'product_stocks'],
    ['wish_report', 'product_wishlist_report', 'wishlists'],
]);

test('a seller cannot enter the admin commission report through a seller id filter', function () {
    actingAsStoreUser(createStoreUser('seller'));
    $this->get('/temp/admin/commission-log?seller_id=1')->assertNotFound();
});

test('history one-sided dates remain usable and non-string ranges are validation errors', function () {
    actingAsStoreUser(createStoreStaff('wallet_transaction_report'));
    Wallet::forceCreate(['user_id' => 1, 'amount' => 5, 'created_at' => '2026-01-02 12:00:00']);
    $this->get('/temp/admin/wallet-history?date_to=2026-01-02')->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows.data', 1));
    $this->get('/temp/admin/wallet-history?date_from=2026-01-03')->assertOk()->assertInertia(fn (Assert $page) => $page->has('rows.data', 0));
    $this->getJson('/temp/admin/wallet-history?'.http_build_query(['date_range' => ['yesterday']]))->assertUnprocessable()->assertJsonValidationErrors('date_range');
});
