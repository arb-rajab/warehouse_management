<?php

use App\Models\Category;
use App\Models\OrderDetail;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The warehouse (WMS client) API under /temp/api/v2/warehouse. Its routes are
 * guarded by WmsAuthMiddlewareTest; these cover what they do once through.
 * Errors there are reported in the body of an HTTP 200, which the client
 * parses, so the tests pin that envelope rather than a status code.
 */
const WAREHOUSE_URL = '/temp/api/v2/warehouse';

beforeEach(function () {
    config(['app.wms_token' => 'wms-secret']);
});

function warehouse()
{
    return test()->withHeaders(['private-token' => 'wms-secret']);
}

// Customer update

test('a partial customer update keeps every field it did not send', function () {
    $customer = createStoreUser('customer');
    $customer->forceFill([
        'phone' => '0612345678', 'country' => 'NL', 'company_name' => 'Old Company',
        'tax_number' => 'TAX-1', 'bank_account_number' => 'NL00BANK', 'company_address' => 'Old Street 1',
        'shipping_address' => 'Old Dock 2',
    ])->save();
    $noise = createStoreUser('customer');

    warehouse()->postJson(WAREHOUSE_URL."/customer/update/{$customer->id}", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('result', true)
        ->assertJsonPath('data.name', 'New Name');

    expect($customer->fresh())
        ->name->toBe('New Name')
        ->phone->toBe('0612345678')
        ->country->toBe('NL')
        ->company_name->toBe('Old Company')
        ->tax_number->toBe('TAX-1')
        ->bank_account_number->toBe('NL00BANK')
        ->company_address->toBe('Old Street 1')
        ->shipping_address->toBe('Old Dock 2')
        ->and($noise->fresh()->name)->toBe($noise->name);
});

test('a customer update can clear a field by sending it empty', function () {
    $customer = createStoreUser('customer');
    $customer->forceFill(['company_name' => 'Old Company'])->save();

    warehouse()->postJson(WAREHOUSE_URL."/customer/update/{$customer->id}", ['company_name' => null])->assertOk();

    expect($customer->fresh()->company_name)->toBeNull()
        ->and($customer->fresh()->name)->toBe($customer->name);
});

test('a customer update rejects over-long and non-string values and changes nothing', function (array $payload) {
    $customer = createStoreUser('customer');

    warehouse()->postJson(WAREHOUSE_URL."/customer/update/{$customer->id}", $payload)
        ->assertOk()
        ->assertJsonPath('result', false);

    expect($customer->fresh()->name)->toBe($customer->name);
})->with([
    'a name over 255' => [['name' => str_repeat('a', 256)]],
    'a company address over 500' => [['company_address' => str_repeat('a', 501)]],
    'a phone over 50' => [['phone' => str_repeat('1', 51)]],
    'an array name' => [['name' => ['x']]],
]);

test('a customer update refuses a bmp, a Word document or a script as the trade license', function (UploadedFile $file) {
    $customer = createStoreUser('customer');

    warehouse()->post(WAREHOUSE_URL."/customer/update/{$customer->id}", ['trade_license' => $file], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('result', false);

    expect($customer->fresh()->trade_license)->toBeNull();
})->with([
    'bmp' => fn () => UploadedFile::fake()->create('licence.bmp', 10, 'image/bmp'),
    'docx' => fn () => UploadedFile::fake()->create('licence.docx', 10),
    'php' => fn () => UploadedFile::fake()->create('licence.php', 10),
]);

test('a customer update for an unknown customer answers the not found envelope', function () {
    warehouse()->postJson(WAREHOUSE_URL.'/customer/update/999999', ['name' => 'X'])
        ->assertOk()
        ->assertJson(['result' => false, 'message' => 'Customer not found', 'status' => 404]);
});

// Lists

test('the customers list returns the first page only and keeps its response shape', function () {
    $customers = collect([createStoreUser('customer'), createStoreUser('customer'), createStoreUser('customer')]);
    $seller = createStoreUser('seller');

    $response = warehouse()->getJson(WAREHOUSE_URL.'/customers?per_page=2')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->all())->toBe($customers->take(2)->pluck('id')->all())
        ->and($ids)->not->toContain($seller->id)
        ->and($response->json('meta.total'))->toBe(3)
        ->and($response->json('success'))->toBeTrue()
        ->and(array_keys($response->json('data.0')))->toContain('otajer_id', 'company_shipping_address', 'registered_at');

    $second = warehouse()->getJson(WAREHOUSE_URL.'/customers?per_page=2&page=2')->assertOk();
    expect(collect($second->json('data'))->pluck('id')->all())->toBe([$customers[2]->id]);
});

test('the reps list returns the first page only', function () {
    $reps = collect(range(1, 3))->map(function () {
        $rep = createStoreUser('customer');
        $rep->forceFill(['is_rep' => true])->save();

        return $rep;
    });
    createStoreUser('customer');

    $response = warehouse()->getJson(WAREHOUSE_URL.'/reps?per_page=2')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.total'))->toBe(3)
        ->and(collect($response->json('data'))->pluck('id')->all())->toBe($reps->take(2)->pluck('id')->all());
});

test('the orders list returns the first page only and skips zero-total orders', function () {
    $customer = createStoreUser('customer');
    $orders = collect(range(1, 3))->map(fn () => createStoreOrder($customer));
    createStoreOrder($customer, ['grand_total' => 0]);

    $response = warehouse()->getJson(WAREHOUSE_URL.'/orders?per_page=2')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.total'))->toBe(3)
        ->and(collect($response->json('data'))->pluck('id')->all())->toBe($orders->take(2)->pluck('id')->all())
        ->and(array_keys($response->json('data.0')))->toContain('customer_id', 'rep_serial', 'for_customer');
});

test('the orders list resolves every rep serial in one query however many rep orders there are', function () {
    $rep = createStoreUser('customer');
    $rep->forceFill(['is_rep' => true, 'rep_serial' => 'REP-7'])->save();
    $customer = createStoreUser('customer');
    foreach (range(1, 5) as $_) {
        createStoreOrder($rep, ['by_rep' => 1, 'for_customer' => $customer->id]);
    }
    $direct = createStoreOrder($customer);

    DB::enableQueryLog();
    $response = warehouse()->getJson(WAREHOUSE_URL.'/orders')->assertOk();
    $userQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from `users`') || str_contains($q['query'], 'from "users"'));

    $byId = collect($response->json('data'))->keyBy('id');
    expect($userQueries)->toHaveCount(1)
        ->and($byId[$direct->id]['rep_serial'])->toBeNull()
        ->and(collect($response->json('data'))->where('by_rep', true)->pluck('rep_serial')->unique()->all())->toBe(['REP-7']);
});

test('the products list returns the first page only', function () {
    foreach (range(1, 3) as $i) {
        Product::factory()->create(['published' => true]);
    }

    $response = warehouse()->getJson(WAREHOUSE_URL.'/products?per_page=2')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('total'))->toBe(3);
});

test('the categories list returns the first page only', function () {
    foreach (['A', 'B', 'C'] as $i => $name) {
        Category::forceCreate(['name' => $name, 'order_level' => $i]);
    }

    $response = warehouse()->getJson(WAREHOUSE_URL.'/categories?per_page=2')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('total'))->toBe(3)
        ->and(collect($response->json('data'))->pluck('name')->all())->toBe(['C', 'B']);
});

test('a list refuses a page size over the maximum', function (string $path) {
    warehouse()->getJson(WAREHOUSE_URL.$path.'?per_page=100000')
        ->assertStatus(422)
        ->assertJsonPath('result', false);
})->with(['/customers', '/reps', '/orders', '/products', '/categories']);

// Order items

test('the order items endpoint loads the order and products once however many lines it has', function () {
    $customer = createStoreUser('customer');
    $order = createStoreOrder($customer);
    $products = collect(range(1, 5))->map(fn ($i) => Product::factory()->create(['serial' => "SER-{$i}"]));
    foreach ($products as $i => $product) {
        OrderDetail::forceCreate([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_serial' => $product->serial,
            'price' => 5, 'tax' => 0, 'quantity' => $i + 1,
        ]);
    }

    DB::enableQueryLog();
    $response = warehouse()->getJson(WAREHOUSE_URL."/order_items/{$order->id}")->assertOk();
    $queries = count(DB::getQueryLog());

    expect($response->json('data'))->toHaveCount(5)
        ->and(array_keys($response->json('data.0')))->toBe([
            'name', 'product_id', 'product_matId', 'product_serial', 'unit_equal', 'unit_id', 'description',
            'quantity', 'variation', 'price', 'original_price', 'tax', 'item_notes', 'variation_quantity',
        ])
        ->and($response->json('data.0.name'))->toBe($products[0]->name)
        ->and($queries)->toBeLessThanOrEqual(3);
});

// Order status

test('update_order marks an order fetched', function () {
    $order = createStoreOrder(createStoreUser('customer'));

    warehouse()->postJson(WAREHOUSE_URL.'/update_order', ['id' => $order->id, 'status' => 'fetched'])
        ->assertOk()
        ->assertJson(['result' => true, 'message' => 'Order Updated Successfully', 'status' => 200]);

    expect($order->fresh())->delivery_status->toBe('confirmed')->wms_status->toBe('Sent to WMS');
});

test('update_order marks an order sent and stores the otajer order id', function () {
    $order = createStoreOrder(createStoreUser('customer'));

    warehouse()->postJson(WAREHOUSE_URL.'/update_order', ['id' => $order->id, 'status' => 'sent', 'otajerOrderID' => 'OT-9'])
        ->assertOk()
        ->assertJsonPath('result', true);

    expect($order->fresh())->delivery_status->toBe('ready_for_delivery')->wms_status->toBe('Sent to Otajer')->otajerOrderID->toBe('OT-9');
});

test('update_order refuses a bad status or a sent order without an otajer id, leaving the order alone', function (array $payload, ?string $message) {
    $order = createStoreOrder(createStoreUser('customer'));

    $response = warehouse()->postJson(WAREHOUSE_URL.'/update_order', ['id' => $order->id, ...$payload])
        ->assertOk()
        ->assertJsonPath('result', false)
        ->assertJsonPath('status', 200);

    if ($message !== null) {
        $response->assertJsonPath('message', $message);
    }

    expect($order->fresh()->wms_status)->toBeNull();
})->with([
    'no status' => [[], 'status must be `fetched` or `sent` '],
    'an unknown status' => [['status' => 'shipped'], 'status must be `fetched` or `sent` '],
    'sent without an otajer id' => [['status' => 'sent'], 'otajerOrderID is missing in the request body'],
    'an over-long otajer id' => [['status' => 'sent', 'otajerOrderID' => str_repeat('1', 256)], null],
]);

test('update_order answers the not found envelope for an unknown order', function () {
    warehouse()->postJson(WAREHOUSE_URL.'/update_order', ['id' => 999999, 'status' => 'fetched'])
        ->assertOk()
        ->assertJson(['result' => false, 'message' => 'Order not found', 'status' => 404]);
});

// Order customer

test('update-customer reassigns a direct order, answers JSON and writes an audit log entry', function () {
    Log::spy();
    $original = createStoreUser('customer');
    $target = createStoreUser('customer');
    $order = createStoreOrder($original);

    warehouse()->postJson(WAREHOUSE_URL.'/order/update-customer', ['order_id' => $order->id, 'customer_id' => $target->id])
        ->assertOk()
        ->assertJson(['result' => true, 'status' => 200]);

    expect($order->fresh()->user_id)->toBe($target->id);
    Log::shouldHaveReceived('info')->once()->with('warehouse.order.customer_updated', Mockery::on(
        fn (array $context) => $context['order_id'] === $order->id
            && $context['customer_id'] === $target->id
            && $context['previous']['user_id'] === $original->id,
    ));
});

test('update-customer on a rep order changes the customer it was placed for and keeps the rep', function () {
    $rep = createStoreUser('customer');
    $target = createStoreUser('customer');
    $target->forceFill(['member_serial' => 'M-42'])->save();
    $order = createStoreOrder($rep, ['by_rep' => 1, 'for_customer' => createStoreUser('customer')->id]);

    warehouse()->postJson(WAREHOUSE_URL.'/order/update-customer', ['order_id' => $order->id, 'customer_id' => $target->id])->assertOk();

    expect($order->fresh())->user_id->toBe($rep->id)->for_customer->toBe($target->id)->member_serial->toBe('M-42');
});

test('update-customer refuses a user who is not a customer and changes nothing', function (string $userType) {
    Log::spy();
    $original = createStoreUser('customer');
    $order = createStoreOrder($original);
    $notCustomer = createStoreUser($userType);

    warehouse()->postJson(WAREHOUSE_URL.'/order/update-customer', ['order_id' => $order->id, 'customer_id' => $notCustomer->id])
        ->assertStatus(422)
        ->assertJson(['result' => false, 'status' => 422]);

    expect($order->fresh()->user_id)->toBe($original->id);
    Log::shouldNotHaveReceived('info', ['warehouse.order.customer_updated', Mockery::any()]);
})->with(['admin', 'seller', 'driver']);

test('update-customer validates its ids and answers 404 for unknown ones', function () {
    $order = createStoreOrder(createStoreUser('customer'));

    warehouse()->postJson(WAREHOUSE_URL.'/order/update-customer', ['order_id' => $order->id])->assertStatus(422)->assertJsonPath('result', false);
    warehouse()->postJson(WAREHOUSE_URL.'/order/update-customer', ['order_id' => 999999, 'customer_id' => 1])->assertNotFound();
    warehouse()->postJson(WAREHOUSE_URL.'/order/update-customer', ['order_id' => $order->id, 'customer_id' => 999999])->assertNotFound();
});

// Throttle

test('the warehouse routes are throttled and token checked by one group', function () {
    $middleware = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'api/v2/warehouse'))
        ->map(fn ($route) => $route->gatherMiddleware());

    expect($middleware)->toHaveCount(11)
        ->and($middleware->every(fn ($m) => in_array('throttle:wms', $m) && in_array('wms.auth', $m)))->toBeTrue();
});

test('the warehouse routes stop answering past the throttle limit, even with a bad token', function () {
    foreach (range(1, 120) as $_) {
        test()->getJson(WAREHOUSE_URL.'/categories');
    }

    test()->getJson(WAREHOUSE_URL.'/categories')->assertStatus(429);
});
