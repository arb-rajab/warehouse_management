<?php

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function adminOrderPayload(): array
{
    $seller = createStoreUser('admin');
    $customer = createStoreUser();
    $collection = DB::table('product_collections')->insertGetId(['name' => 'Margin', 'serial' => 'MARGIN', 'amount' => 10]);
    $product = createStoreProduct(['user_id' => $seller->id, 'approved' => 1, 'unit_price' => 12, 'unit_equal' => 6, 'tax' => 21, 'serial' => 'SERIAL-'.Str::random(8), 'collection_id' => $collection]);

    return ['submission_key' => (string) Str::uuid(), 'customer_id' => $customer->id, 'products' => [['product_id' => $product->id, 'quantity' => 3, 'price' => 10, 'unit' => 'PerBox', 'stock_variant' => '', 'info' => 'Entry notes']]];
}

beforeEach(fn () => Notification::fake());

test('new shipping orders calculate tax, line totals, margins and stock exactly once', function () {
    actingAsStoreUser(createStoreStaff('add_order'));
    $payload = adminOrderPayload();
    $this->post(route('new.feat-order.trip.store'), $payload)->assertRedirect();
    $order = Order::firstOrFail();
    $line = $order->orderDetails()->firstOrFail();
    expect((float) $line->price)->toBe(30.0)->and((float) $line->tax)->toBe(6.3)
        ->and((float) $line->getAttribute('original_price'))->toBe(36.0)
        ->and((float) $order->grand_total)->toBe(36.3)
        ->and((float) $order->getAttribute('profits'))->toBe(3.0)
        ->and((float) $order->getAttribute('discount_percent'))->toBe(16.67)
        ->and((float) $order->getAttribute('indicator'))->toBe(10.0);
    $this->post(route('new.feat-order.trip.store'), $payload)->assertRedirect(route('all_orders.show', $order));
    expect(Order::count())->toBe(1)->and(DB::table('product_stocks')->where('product_id', $line->product_id)->value('qty'))->toBe(47);
    $payload['products'][0]['price'] = 9;
    $this->postJson(route('new.feat-order.trip.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('submission_key');
    expect(Order::count())->toBe(1);
});

test('seller groups share one combined total and an unavailable later line rolls the entire submission back', function () {
    actingAsStoreUser(createStoreStaff('add_order'));
    $payload = adminOrderPayload();
    $seller = createStoreUser('seller');
    $second = createStoreProduct(['user_id' => $seller->id, 'approved' => 1, 'current_stock' => 1]);
    $payload['products'][] = ['product_id' => $second->id, 'quantity' => 2, 'price' => 4, 'unit' => 'PerPiece', 'stock_variant' => ''];
    $this->postJson(route('new.feat-order.trip.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('products');
    expect(Order::count())->toBe(0)->and(DB::table('combined_orders')->count())->toBe(0)
        ->and(DB::table('wms_store_order_submissions')->count())->toBe(0)
        ->and(DB::table('product_stocks')->where('product_id', $payload['products'][0]['product_id'])->value('qty'))->toBe(50);
    $payload['products'][1]['quantity'] = 1;
    $this->post(route('new.feat-order.trip.store'), $payload)->assertRedirect();
    expect(Order::count())->toBe(2)->and((float) DB::table('combined_orders')->value('grand_total'))->toBe(40.3);
});

test('representative entry requires a representative and piece originals use the box conversion', function () {
    actingAsStoreUser(createStoreStaff('add_order'));
    $payload = adminOrderPayload();
    $this->postJson(route('feat-order.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('rep_id');
    $rep = createStoreUser();
    $rep->forceFill(['is_rep' => 1])->save();
    $payload['rep_id'] = $rep->id;
    $payload['products'][0]['unit'] = 'PerPiece';
    $payload['products'][0]['price'] = 0;
    $this->post(route('feat-order.store'), $payload)->assertRedirect();
    $order = Order::firstOrFail();
    expect($order->by_rep)->toBeTrue()->and($order->for_customer)->toBe($payload['customer_id'])
        ->and((float) $order->orderDetails()->value('original_price'))->toBe(6.0)
        ->and((float) $order->grand_total)->toBe(0.0)->and((float) $order->getAttribute('indicator'))->toBe(0.0);
});

test('edits preserve stored tax shipping serials and variants, adjust stock, and reject duplicate or foreign lines', function () {
    actingAsStoreUser(createStoreStaff('add_order', 'edit_order'));
    $payload = adminOrderPayload();
    $this->post(route('new.feat-order.trip.store'), $payload);
    $order = Order::firstOrFail();
    $line = $order->orderDetails()->firstOrFail();
    $line->forceFill(['shipping_cost' => 4, 'variation_qty' => '{"boxes":3}'])->save();
    unset($payload['submission_key']);
    $payload['products'][0]['id'] = $line->id;
    $payload['products'][0]['quantity'] = 2;
    $this->put(route('new.feat-order.trip.update', $order), $payload)->assertRedirect();
    expect((float) $order->fresh()->grand_total)->toBe(30.3)
        ->and((float) $line->fresh()->tax)->toBe(6.3)
        ->and($line->fresh()->getAttribute('variation_qty'))->toBe('{"boxes":3}')
        ->and($line->fresh()->getAttribute('product_serial'))->not->toBeNull()
        ->and(DB::table('product_stocks')->where('product_id', $line->product_id)->value('qty'))->toBe(48);
    $payload['products'][] = $payload['products'][0];
    $this->putJson(route('new.feat-order.trip.update', $order), $payload)->assertUnprocessable()->assertJsonValidationErrors('products.0.id');
    expect(DB::table('product_stocks')->where('product_id', $line->product_id)->value('qty'))->toBe(48);
    array_pop($payload['products']);
    $payload['products'][0]['id'] = 999999;
    $this->putJson(route('new.feat-order.trip.update', $order), $payload)->assertUnprocessable()->assertJsonValidationErrors('products');
    expect(DB::table('product_stocks')->where('product_id', $line->product_id)->value('qty'))->toBe(48);
});

test('cancellation restocks legacy unit lines and refunds wallets once, then refuses terminal changes', function () {
    actingAsStoreUser(createStoreStaff('add_order', 'update_order_delivery_status'));
    $payload = adminOrderPayload();
    $this->post(route('new.feat-order.trip.store'), $payload);
    $order = Order::firstOrFail();
    $order->forceFill(['payment_type' => 'wallet'])->save();
    $order->orderDetails()->update(['entry_unit' => null, 'variation' => 'PerBox']);
    $url = route('orders.update_delivery_status');
    $this->post($url, ['order_id' => $order->id, 'status' => 'cancelled'])->assertRedirect();
    $this->post($url, ['order_id' => $order->id, 'status' => 'cancelled'])->assertRedirect();
    expect(DB::table('product_stocks')->where('product_id', $payload['products'][0]['product_id'])->value('qty'))->toBe(50)
        ->and((float) StoreUser::findOrFail($payload['customer_id'])->balance)->toBe(36.3);
    $this->postJson($url, ['order_id' => $order->id, 'status' => 'pending'])->assertUnprocessable();
    expect($order->fresh()->delivery_status)->toBe('cancelled');
});

test('payment transitions calculate commission only once and block paid edits and reversals', function () {
    actingAsStoreUser(createStoreStaff('add_order', 'edit_order', 'edit_order_price', 'update_order_payment_status'));
    $payload = adminOrderPayload();
    $this->post(route('new.feat-order.trip.store'), $payload);
    $order = Order::firstOrFail();
    $url = route('orders.update_payment_status');
    $this->post($url, ['order_id' => $order->id, 'status' => 'paid'])->assertRedirect();
    $this->post($url, ['order_id' => $order->id, 'status' => 'paid'])->assertRedirect();
    expect((int) $order->fresh()->getAttribute('commission_calculated'))->toBe(1);
    $this->postJson($url, ['order_id' => $order->id, 'status' => 'unpaid'])->assertUnprocessable();
    $this->putJson(route('new.feat-order.trip.update', $order), $payload)->assertUnprocessable();
    $this->postJson(route('order.item.edit_price'), ['id' => $order->orderDetails()->value('id'), 'admin_price' => 1])->assertUnprocessable();
    expect((float) $order->fresh()->grand_total)->toBe(36.3);
});

$writeRoutes = ['feat-order.store', 'new.feat-order.trip.store', 'new.feat-order.trip.update', 'orders.destroy', 'bulk-order-delete', 'orders.update_delivery_status', 'orders.update_payment_status', 'orders.update_tracking_code', 'orders.update_manager_notes', 'order.item.edit_price'];
test('every order write refuses unauthorized callers without changing orders stock or balances', function (string $route, string $caller) {
    $payload = adminOrderPayload();
    $order = createStoreOrder(StoreUser::findOrFail($payload['customer_id']));
    $before = [DB::table('orders')->get()->toJson(), DB::table('product_stocks')->get()->toJson(), DB::table('users')->get()->toJson()];
    actingAsRefusedStoreCaller($caller);
    // Account setup belongs before the snapshot; compare business fields instead of all account rows.
    $before[2] = DB::table('users')->where('id', $payload['customer_id'])->get()->toJson();
    $response = match ($route) {
        'new.feat-order.trip.update' => $this->put(route($route, $order), $payload),
        'orders.destroy' => $this->delete(route($route, $order)),
        default => $this->post(route($route), [...$payload, 'order_id' => $order->id, 'id' => [$order->id], 'status' => 'paid', 'notes' => 'changed', 'admin_price' => 0]),
    };
    assertStoreCallerRefused($response, $caller);
    expect(DB::table('orders')->get()->toJson())->toBe($before[0])
        ->and(DB::table('product_stocks')->get()->toJson())->toBe($before[1])
        ->and(DB::table('users')->where('id', $payload['customer_id'])->get()->toJson())->toBe($before[2]);
})->with($writeRoutes)->with('refused store admin callers');

test('order lists paginate and group search within seller and pickup scopes', function () {
    $admin = createStoreUser('admin');
    $staff = createStoreStaff('view_all_orders', 'view_inhouse_orders', 'view_pickup_point_orders');
    $staffRow = createStaffRow($staff);
    $pickup = DB::table('pickup_points')->insertGetId(['name' => 'Own pickup', 'staff_id' => $staffRow->id, 'address' => 'Pickup street', 'phone' => '0612345678']);
    $customer = createStoreUser();
    $customer->forceFill(['AccSysID' => 'MATCH'])->save();
    for ($i = 0; $i < 21; $i++) {
        createStoreOrder($customer, ['seller_id' => $admin->id]);
    }
    createStoreOrder($customer, ['seller_id' => 999, 'shipping_type' => 'pickup_point', 'pickup_point_id' => $pickup + 1]);
    $own = createStoreOrder($customer, ['seller_id' => $admin->id, 'shipping_type' => 'pickup_point', 'pickup_point_id' => $pickup]);
    actingAsStoreUser($staff);
    $this->get(route('all_orders.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Sales/Orders/Index')->has('orders.data', 20)->where('orders.meta.total', 23));
    $this->get(route('inhouse_orders.index', ['search' => 'MATCH']))->assertOk()->assertInertia(fn (Assert $page) => $page->where('orders.meta.total', 22));
    $this->get(route('pick_up_point.index', ['search' => 'MATCH']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $own->id));
    $this->getJson(route('all_orders.index', ['date' => 'bad']))->assertUnprocessable();
});

test('search returns only eligible options in bounded pages without credentials or HTML', function () {
    actingAsStoreUser(createStoreStaff('add_order'));
    for ($i = 0; $i < 21; $i++) {
        createStoreUser()->forceFill(['name' => 'Find Customer '.$i])->save();
    }
    createStoreUser('seller')->forceFill(['name' => 'Find Seller'])->save();
    $this->getJson(route('orders.search-options', ['kind' => 'customers', 'q' => 'Find']))->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 21)->assertJsonMissingPath('data.0.password');
    $payload = adminOrderPayload();
    createStoreProduct(['name' => 'Hidden', 'approved' => 0]);
    $this->getJson(route('orders.search-options', ['kind' => 'products']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $payload['products'][0]['product_id'])->assertJsonMissingPath('data.0.description');
});

test('detail aliases accept legacy encrypted links, expose narrow escaped props and return 404 for bad IDs', function () {
    actingAsStoreUser(createStoreStaff('view_order_details'));
    $order = createStoreOrder(createStoreUser());
    foreach (['orders.show', 'all_orders.show', 'inhouse_orders.show', 'seller_orders.show', 'pick_up_point.order_show'] as $name) {
        $this->get(route($name, encrypt($order->id)))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Sales/Orders/Show')->where('order.id', $order->id)->missing('order.customer.password')->missing('order.user'));
    }
    $this->get(route('all_orders.show', 'bad-cipher'))->assertNotFound();
    $this->get(route('all_orders.show', 999999))->assertNotFound();
    expect((int) $order->fresh()->viewed)->toBe(1);
});

test('authorized price tracking and notes writes preserve totals and enforce validation', function () {
    actingAsStoreUser(createStoreStaff('add_order', 'edit_order_price', 'update_order_tracking', 'update_order_notes'));
    $payload = adminOrderPayload();
    $this->post(route('new.feat-order.trip.store'), $payload);
    $order = Order::firstOrFail();
    $line = $order->orderDetails()->firstOrFail();
    $this->post(route('order.item.edit_price'), ['id' => $line->id, 'admin_price' => 8])->assertRedirect();
    expect((float) $order->fresh()->grand_total)->toBe(30.3)
        ->and((float) $order->fresh()->getAttribute('profits'))->toBe(2.4)
        ->and((float) StoreUser::findOrFail($payload['customer_id'])->getAttribute('profits'))->toBe(2.4);
    $this->post(route('orders.update_tracking_code'), ['order_id' => $order->id, 'tracking_code' => 'TRACK'])->assertRedirect();
    $this->post(route('orders.update_manager_notes'), ['id' => $order->id, 'notes' => 'Office'])->assertRedirect();
    expect($order->fresh()->tracking_code)->toBe('TRACK')->and($order->fresh()->getAttribute('manager_notes'))->toBe('Office');
    $this->postJson(route('order.item.edit_price'), ['id' => $line->id, 'admin_price' => -1])->assertUnprocessable();
    $this->postJson(route('orders.update_tracking_code'), ['order_id' => $order->id, 'tracking_code' => str_repeat('x', 256)])->assertUnprocessable();
    $this->postJson(route('orders.update_manager_notes'), ['order_id' => $order->id, 'notes' => str_repeat('x', 2001)])->assertUnprocessable();
    $this->post(route('orders.update_tracking_code'), ['order_id' => 999999, 'tracking_code' => 'TRACK'])->assertNotFound();
});

test('deletion restocks once, recalculates combined totals and leaves the replay tombstone', function () {
    actingAsStoreUser(createStoreStaff('add_order', 'delete_order'));
    $payload = adminOrderPayload();
    $this->post(route('new.feat-order.trip.store'), $payload);
    $order = Order::firstOrFail();
    $this->delete(route('orders.destroy', $order))->assertRedirect(route('all_orders.index'));
    expect(Order::count())->toBe(0)->and(OrderDetail::count())->toBe(0)
        ->and(DB::table('product_stocks')->where('product_id', $payload['products'][0]['product_id'])->value('qty'))->toBe(50)
        ->and((float) DB::table('combined_orders')->value('grand_total'))->toBe(0.0)
        ->and(DB::table('wms_store_order_submissions')->count())->toBe(1)
        ->and((float) StoreUser::findOrFail($payload['customer_id'])->getAttribute('profits'))->toBe(0.0);
    $this->post(route('new.feat-order.trip.store'), $payload)->assertNotFound();
    expect(Order::count())->toBe(0);
});

test('stock reservation matches the exact variant, leaving similarly named stock untouched', function () {
    actingAsStoreUser(createStoreStaff('add_order', 'update_order_delivery_status'));
    $payload = adminOrderPayload();
    $productId = $payload['products'][0]['product_id'];
    DB::table('product_stocks')->where('product_id', $productId)->update(['variant' => 'Red-Large']);
    DB::table('product_stocks')->insert(['product_id' => $productId, 'variant' => 'Red', 'price' => 12, 'qty' => 10]);
    $payload['products'][0]['stock_variant'] = 'Red';
    $this->post(route('new.feat-order.trip.store'), $payload)->assertRedirect();
    expect(DB::table('product_stocks')->where('product_id', $productId)->where('variant', 'Red')->value('qty'))->toBe(7)
        ->and(DB::table('product_stocks')->where('product_id', $productId)->where('variant', 'Red-Large')->value('qty'))->toBe(50);
    $this->post(route('orders.update_delivery_status'), ['order_id' => Order::firstOrFail()->id, 'status' => 'cancelled'])->assertRedirect();
    expect(DB::table('product_stocks')->where('product_id', $productId)->where('variant', 'Red')->value('qty'))->toBe(10);
});

test('every order read refuses unauthorized callers', function (string $route, string $caller) {
    $order = createStoreOrder(createStoreUser());
    actingAsRefusedStoreCaller($caller);
    $params = match ($route) {
        'all_orders.show', 'new.feat-order.trip.edit' => [$order],
        'orders.search-options' => ['kind' => 'customers'],
        default => [],
    };
    assertStoreCallerRefused($this->get(route($route, $params)), $caller);
})->with(['all_orders.index', 'inhouse_orders.index', 'seller_orders.index', 'pick_up_point.index', 'all_orders.show', 'feat-order.create', 'new.feat-order.trip.create', 'new.feat-order.trip.edit', 'orders.search-options'])->with('refused store admin callers');

test('create and edit render bounded entry props and missing bound models return 404', function () {
    actingAsStoreUser(createStoreStaff('add_order', 'edit_order', 'delete_order'));
    $this->get(route('feat-order.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Sales/Orders/Create')->where('representativeOrder', true)->has('submissionKey')->missing('products')->missing('customers'));
    $this->get(route('new.feat-order.trip.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('representativeOrder', false));
    $payload = adminOrderPayload();
    $this->post(route('new.feat-order.trip.store'), $payload);
    $order = Order::firstOrFail();
    $this->get(route('new.feat-order.trip.edit', $order))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Sales/Orders/Edit')->where('order.id', $order->id)->where('order.editable', true)->has('order.details', 1)->where('order.details.0.unit_price', 10)->missing('order.customer.password'));
    $this->get(route('new.feat-order.trip.edit', 999999))->assertNotFound();
    $this->put(route('new.feat-order.trip.update', 999999), $payload)->assertNotFound();
    $this->delete(route('orders.destroy', 999999))->assertNotFound();
    expect(Order::count())->toBe(1);
});

test('bulk deletion rolls back earlier stock and order changes when a later order is settled', function () {
    actingAsStoreUser(createStoreStaff('add_order', 'delete_order'));
    $payload = adminOrderPayload();
    $this->post(route('new.feat-order.trip.store'), $payload);
    $first = Order::firstOrFail();
    $payload['submission_key'] = (string) Str::uuid();
    $this->post(route('new.feat-order.trip.store'), $payload);
    $second = Order::orderByDesc('id')->firstOrFail();
    $second->forceFill(['payment_status' => 'paid', 'commission_calculated' => 1])->save();
    $this->postJson(route('bulk-order-delete'), ['id' => [$first->id, $second->id]])->assertUnprocessable();
    expect(Order::count())->toBe(2)->and(DB::table('product_stocks')->where('product_id', $payload['products'][0]['product_id'])->value('qty'))->toBe(44);
    $second->forceFill(['payment_status' => 'unpaid', 'commission_calculated' => 0])->save();
    $this->post(route('bulk-order-delete'), ['id' => [$first->id, $second->id]])->assertRedirect();
    expect(Order::count())->toBe(0)->and(DB::table('product_stocks')->where('product_id', $payload['products'][0]['product_id'])->value('qty'))->toBe(50);
});

test('editing a customer maintains combined ownership and refuses splitting ownership across sellers', function () {
    actingAsStoreUser(createStoreStaff('add_order', 'edit_order'));
    $payload = adminOrderPayload();
    $this->post(route('new.feat-order.trip.store'), $payload);
    $order = Order::firstOrFail();
    $payload['customer_id'] = createStoreUser()->id;
    $payload['products'][0]['id'] = $order->orderDetails()->value('id');
    $this->put(route('new.feat-order.trip.update', $order), $payload)->assertRedirect();
    expect((int) DB::table('combined_orders')->value('user_id'))->toBe($payload['customer_id']);
    createStoreOrder(createStoreUser(), ['combined_order_id' => $order->combined_order_id]);
    $payload['customer_id'] = createStoreUser()->id;
    $this->putJson(route('new.feat-order.trip.update', $order), $payload)->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    expect($order->fresh()->user_id)->not->toBe($payload['customer_id']);
});

test('order pagination accepts documented sizes and rejects unbounded sizes', function () {
    actingAsStoreUser(createStoreStaff('view_all_orders'));
    $customer = createStoreUser();
    for ($i = 0; $i < 12; $i++) {
        createStoreOrder($customer);
    }
    $this->get(route('all_orders.index', ['per_page' => 10]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders.data', 10)->where('orders.meta.total', 12)->where('filters.per_page', 10));
    $this->getJson(route('all_orders.index', ['per_page' => 100000]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
});
