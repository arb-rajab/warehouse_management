<?php

use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\Upload;
use Inertia\Testing\AssertableInertia as Assert;

// The list prints each grand total through single_price(), which reads the
// default currency.
beforeEach(function () {
    $this->withoutVite();
    seedStoreCurrency();
});

/**
 * A trip on its own truck holding `$order` as a delivery checkpoint.
 */
function tripOrderCheckpoint(Order $order, array $attributes = [], ?Trip $trip = null): Checkpoint
{
    $trip ??= Trip::create(['code' => 'Trip-'.random_int(1, 99999999), 'truck_id' => Truck::create(['name' => 'Order truck'])->id, 'due_date' => '2026-10-10', 'status' => 'pending', 'active' => true]);

    return $trip->checkpoints()->create([
        'relationable_type' => Order::class,
        'relationable_id' => $order->id,
        'type' => 'delivery',
        'status' => 'pending',
        'sort_order_in_trip' => 1,
        ...$attributes,
    ])->fresh();
}

/**
 * The delivery status and invoice number of every order, keyed by id.
 *
 * @return array<int, array{delivery_status: string, invoice_number: string|null}>
 */
function tripOrderStates(): array
{
    return Order::query()->orderBy('id')->get(['id', 'delivery_status', 'invoice_number'])
        ->mapWithKeys(fn (Order $order) => [$order->id => $order->only(['delivery_status', 'invoice_number'])])->all();
}

describe('trip orders list', function () {
    test('the list renders every row field for an order with a trip and one without', function () {
        $customer = createStoreUser();
        $customer->forceFill(['name' => 'Alma Customer', 'AccSysID' => 7007, 'company_name' => 'Alma BV', 'shipping_address' => 'Fallback street 1'])->save();
        $rep = createStoreUser();
        $rep->forceFill(['name' => 'Rita Rep'])->save();
        $driver = createStoreUser('driver');
        $driver->forceFill(['name' => 'Sam Driver'])->save();
        $photo = Upload::create(['file_original_name' => 'cmr.jpg', 'type' => 'image']);
        $photo->forceFill(['external_link' => 'https://cdn.example.com/cmr.jpg'])->save();
        $second = Upload::create(['file_original_name' => 'door.jpg', 'type' => 'image']);
        $second->forceFill(['external_link' => 'https://cdn.example.com/door.jpg'])->save();
        $unrelated = Upload::create(['file_original_name' => 'noise.jpg', 'type' => 'image']);
        $unrelated->forceFill(['external_link' => 'https://cdn.example.com/noise.jpg'])->save();
        $order = createStoreOrder($rep, [
            'code' => '20261015-0001', 'by_rep' => 1, 'for_customer' => $customer->id, 'viewed' => 0,
            'otajerOrderID' => 'OT-9', 'order_from' => 'app', 'wms_status' => 'picked',
            'shipping_address' => json_encode(['address' => 'Main street 5', 'country' => 'Netherlands', 'phone' => '0611111111']),
            'invoice_number' => 'INV-1', 'additional_info' => 'Ring twice', 'manager_notes' => 'Handle with care',
            'grand_total' => 42.5, 'delivery_status' => 'on_delivery', 'delivery_date' => '2026-10-16', 'payment_status' => 'unpaid',
        ]);
        $order->orderDetails()->forceCreate(['product_id' => createStoreProduct()->id, 'quantity' => 1, 'price' => 42.5]);
        $earlier = tripOrderCheckpoint($order);
        $trip = Trip::create(['code' => 'TRIP-LAST', 'driver_id' => $driver->id, 'truck_id' => Truck::create(['name' => 'Cargo truck'])->id, 'due_date' => '2026-10-15', 'status' => 'started', 'active' => true]);
        $latest = tripOrderCheckpoint($order, ['photos' => $photo->id.',abc,'.$second->id], $trip);
        $plain = createStoreOrder($customer, ['code' => '20261014-0001', 'viewed' => 1, 'shipping_address' => '', 'delivery_status' => 'confirmed', 'payment_status' => 'unpaid', 'grand_total' => 5]);
        $plain->forceFill(['created_at' => now()->subDay()])->save();
        actingAsStoreUser(createStoreStaff('view_all_trips'));

        $this->get(route('trips.orders'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/Admin/Trips/Orders/Index')
            ->where('filters', ['per_page' => 20, 'search' => '', 'delivery_status' => '', 'from' => '', 'to' => ''])
            ->where('deliveryStatuses', ['confirmed', 'ready_for_delivery', 'on_delivery', 'delivered'])
            ->where('bulkStatuses', ['confirmed', 'ready_for_delivery'])
            ->where('photos', [
                ['id' => $photo->id, 'name' => 'cmr.jpg', 'url' => 'https://cdn.example.com/cmr.jpg'],
                ['id' => $second->id, 'name' => 'door.jpg', 'url' => 'https://cdn.example.com/door.jpg'],
            ])
            ->has('orders.data', 2)
            ->has('orders.data.0', fn (Assert $row) => $row
                ->where('id', $order->id)->where('code', '20261015-0001')->where('is_new', true)->where('details_count', 1)
                ->where('otajer_order_id', 'OT-9')->where('order_from', 'app')->where('wms_status', 'picked')
                ->where('customer', ['id' => $customer->id, 'name' => 'Alma Customer', 'accounting_id' => 7007, 'company_name' => 'Alma BV'])
                ->where('by_rep', true)->where('representative', ['id' => $rep->id, 'name' => 'Rita Rep'])
                ->where('address', 'Main street 5')->where('country', 'Netherlands')->where('phone', '0611111111')
                ->where('invoice_number', 'INV-1')->where('customer_notes', 'Ring twice')->where('manager_notes', 'Handle with care')
                ->where('amount', single_price(42.5))->where('delivery_status', 'on_delivery')->where('delivery_date', '2026-10-16')
                ->where('editable', false)
                ->where('checkpoint', ['id' => $latest->id, 'photos' => [$photo->id, $second->id]])
                ->where('trip', ['id' => $trip->id, 'code' => 'TRIP-LAST', 'driver' => ['id' => $driver->id, 'name' => 'Sam Driver'], 'truck' => ['id' => $trip->truck_id, 'name' => 'Cargo truck']]))
            ->has('orders.data.1', fn (Assert $row) => $row
                ->where('id', $plain->id)->where('code', '20261014-0001')->where('is_new', false)->where('details_count', 0)
                ->where('otajer_order_id', null)->where('order_from', null)->where('wms_status', null)
                ->where('customer', ['id' => $customer->id, 'name' => 'Alma Customer', 'accounting_id' => 7007, 'company_name' => 'Alma BV'])
                ->where('by_rep', false)->where('representative', null)
                ->where('address', 'Fallback street 1')->where('country', null)->where('phone', $customer->phone)
                ->where('invoice_number', null)->where('customer_notes', null)->where('manager_notes', null)
                ->where('amount', single_price(5))->where('delivery_status', 'confirmed')->where('delivery_date', null)
                ->where('editable', true)->where('checkpoint', null)->where('trip', null)));
        expect($earlier->id)->toBeLessThan($latest->id);
    });

    test('the list hides pending and cancelled orders and filters by status, date and search', function () {
        $customer = createStoreUser();
        $customer->forceFill(['name' => 'Alma Customer', 'AccSysID' => 7007])->save();
        $other = createStoreUser();
        $other->forceFill(['name' => 'Bram Buyer'])->save();
        $confirmed = createStoreOrder($customer, ['code' => 'C-1', 'delivery_status' => 'confirmed']);
        $ready = createStoreOrder($other, ['code' => 'R-1', 'delivery_status' => 'ready_for_delivery', 'invoice_number' => 'INV-77']);
        $delivered = createStoreOrder($other, ['code' => 'D-1', 'delivery_status' => 'delivered', 'shipping_address' => json_encode(['address' => 'Harbour road 3'])]);
        $rep = createStoreUser();
        $rep->forceFill(['name' => 'Rita Rep'])->save();
        $repOrder = createStoreOrder($rep, ['code' => 'P-1', 'delivery_status' => 'on_delivery', 'by_rep' => 1, 'for_customer' => $customer->id, 'shipping_address' => json_encode(['address' => 'Depot lane 9'])]);
        $pending = createStoreOrder($customer, ['code' => 'C-2', 'delivery_status' => 'pending']);
        $cancelled = createStoreOrder($customer, ['code' => 'C-3', 'delivery_status' => 'cancelled']);
        foreach ([[$confirmed, '2026-10-01 10:00:00'], [$ready, '2026-10-05 10:00:00'], [$delivered, '2026-10-09 23:30:00'], [$repOrder, '2026-10-10 00:00:00'], [$pending, '2026-10-04 10:00:00'], [$cancelled, '2026-10-06 10:00:00']] as [$order, $at]) {
            $order->forceFill(['created_at' => $at])->save();
        }
        actingAsStoreUser(createStoreStaff('view_all_trips'));
        $ids = fn (array $query) => array_column($this->get(route('trips.orders', $query))->assertOk()->inertiaProps('orders.data'), 'id');

        expect($ids([]))->toBe([$repOrder->id, $delivered->id, $ready->id, $confirmed->id])
            ->and($ids(['delivery_status' => 'ready_for_delivery']))->toBe([$ready->id])
            ->and($ids(['from' => '2026-10-05', 'to' => '2026-10-09']))->toBe([$delivered->id, $ready->id])
            ->and($ids(['search' => 'C-1']))->toBe([$confirmed->id])
            ->and($ids(['search' => 'INV-77']))->toBe([$ready->id])
            ->and($ids(['search' => 'Harbour']))->toBe([$delivered->id])
            ->and($ids(['search' => 'Alma']))->toBe([$repOrder->id, $confirmed->id])
            ->and($ids(['search' => '7007']))->toBe([$repOrder->id, $confirmed->id])
            ->and($ids(['search' => 'Bram']))->toBe([$delivered->id, $ready->id])
            ->and($ids(['search' => 'Rita']))->toBe([])
            ->and($ids(['search' => $customer->phone]))->toBe([$repOrder->id, $confirmed->id])
            ->and($ids(['search' => 'Alma', 'delivery_status' => 'confirmed']))->toBe([$confirmed->id]);
        $this->get(route('trips.orders', ['search' => 'Alma', 'delivery_status' => 'confirmed', 'from' => '2026-10-01', 'to' => '2026-10-02']))
            ->assertInertia(fn (Assert $page) => $page->where('filters', ['per_page' => 20, 'search' => 'Alma', 'delivery_status' => 'confirmed', 'from' => '2026-10-01', 'to' => '2026-10-02']));
    });

    test('the status filter no longer sticks between visits', function () {
        $customer = createStoreUser();
        $confirmed = createStoreOrder($customer, ['delivery_status' => 'confirmed']);
        $delivered = createStoreOrder($customer, ['delivery_status' => 'delivered']);
        actingAsStoreUser(createStoreStaff('view_all_trips'));

        expect(array_column($this->get(route('trips.orders', ['delivery_status' => 'delivered']))->inertiaProps('orders.data'), 'id'))->toBe([$delivered->id])
            ->and(array_column($this->get(route('trips.orders'))->inertiaProps('orders.data'), 'id'))->toEqualCanonicalizing([$confirmed->id, $delivered->id]);
    });

    test('orders paginate twenty at a time with an allowed page size and refuse other filters', function () {
        $customer = createStoreUser();
        for ($i = 0; $i < 21; $i++) {
            createStoreOrder($customer, ['delivery_status' => 'confirmed', 'code' => 'PAGE-'.$i]);
        }
        actingAsStoreUser(createStoreStaff('view_all_trips'));

        assertInertiaPaginates($this->get(route('trips.orders')), 'orders', 20, 21, 'Store/Admin/Trips/Orders/Index');
        $this->get(route('trips.orders', ['page' => 2]))->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
        $this->get(route('trips.orders', ['per_page' => 10, 'search' => 'PAGE']))->assertInertia(fn (Assert $page) => $page->has('orders.data', 10)->where('filters.per_page', 10));
        $this->get(route('trips.orders', ['per_page' => 999]))->assertSessionHasErrors('per_page');
        $this->get(route('trips.orders', ['search' => ['bad']]))->assertSessionHasErrors('search');
        $this->get(route('trips.orders', ['delivery_status' => 'pending']))->assertSessionHasErrors('delivery_status');
        $this->get(route('trips.orders', ['from' => '10-10-2026']))->assertSessionHasErrors('from');
        $this->get(route('trips.orders', ['from' => '2026-10-10', 'to' => '2026-10-09']))->assertSessionHasErrors('to');
    });

    test('the list refuses callers without permission', function (string $caller) {
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get(route('trips.orders')), $caller);
    })->with('refused store admin callers');
});

/** Valid action fixtures always have an explicit trip attachment. */
function attachedTripWriteOrder(StoreUser $customer, array $attributes = []): Order
{
    $order = createStoreOrder($customer, $attributes);
    tripOrderCheckpoint($order);

    return $order;
}

function tripWriteOrderContext(Order $order): array
{
    return [$order, 'trip_id' => $order->checkpoints()->firstOrFail()->trip_id];
}

describe('trip order actions', function () {
    test('the bulk action sets the chosen status on every selected order and returns to the list with its filters', function (string $status) {
        $customer = createStoreUser();
        $first = attachedTripWriteOrder($customer, ['delivery_status' => 'on_delivery']);
        $second = attachedTripWriteOrder($customer, ['delivery_status' => 'delivered']);
        $second->checkpoints()->update(['trip_id' => $first->checkpoints()->firstOrFail()->trip_id]);
        $untouched = attachedTripWriteOrder($customer, ['delivery_status' => 'on_delivery']);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->post(route('trips.orders.bulk-change-delivery-status', ['search' => 'x', 'page' => 2]), ['trip_id' => $first->checkpoints()->firstOrFail()->trip_id, 'id' => [$first->id, $second->id], 'delivery_status' => $status])
            ->assertRedirect(route('trips.orders', ['search' => 'x', 'page' => 2]));

        expect($first->fresh()->delivery_status)->toBe($status)
            ->and($second->fresh()->delivery_status)->toBe($status)
            ->and($untouched->fresh()->delivery_status)->toBe('on_delivery');
    })->with(['confirmed', 'ready_for_delivery']);

    test('the bulk action validates its selection and status without writing', function (array $payload, string $error) {
        $order = attachedTripWriteOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
        actingAsStoreUser(createStoreStaff('edit_trip'));
        $before = tripOrderStates();

        $this->post(route('trips.orders.bulk-change-delivery-status'), array_map(fn ($value) => $value === 'ORDER' ? [$order->id] : $value, $payload))
            ->assertSessionHasErrors($error);

        expect(tripOrderStates())->toBe($before);
    })->with([
        'no selection' => [['delivery_status' => 'confirmed'], 'id'],
        'empty selection' => [['id' => [], 'delivery_status' => 'confirmed'], 'id'],
        'unknown order' => [['id' => [999999], 'delivery_status' => 'confirmed'], 'id.0'],
        'not an id' => [['id' => ['abc'], 'delivery_status' => 'confirmed'], 'id.0'],
        'over a hundred' => [['id' => range(1, 101), 'delivery_status' => 'confirmed'], 'id'],
        'no status' => [['id' => 'ORDER'], 'delivery_status'],
        'a status the action never sets' => [['id' => 'ORDER', 'delivery_status' => 'delivered'], 'delivery_status'],
    ]);

    test('the bulk action refuses duplicate ids', function () {
        $order = attachedTripWriteOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->post(route('trips.orders.bulk-change-delivery-status'), ['id' => [$order->id, $order->id], 'delivery_status' => 'confirmed'])
            ->assertSessionHasErrors('id.0');
        expect($order->fresh()->delivery_status)->toBe('on_delivery');
    });

    test('saving an invoice number stores it, sends the order out for delivery and returns to the list', function (?string $invoice) {
        $order = attachedTripWriteOrder(createStoreUser(), ['delivery_status' => 'delivered', 'invoice_number' => 'OLD-1']);
        $other = attachedTripWriteOrder(createStoreUser(), ['delivery_status' => 'confirmed', 'invoice_number' => 'OTHER']);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->put(route('trips.orders.update_invoice_number', [...tripWriteOrderContext($order), 'delivery_status' => 'delivered']), ['invoice_number' => $invoice])
            ->assertRedirect(route('trips.orders', ['delivery_status' => 'delivered']));

        expect($order->fresh()->only(['invoice_number', 'delivery_status']))->toBe(['invoice_number' => $invoice === '' ? null : $invoice, 'delivery_status' => 'on_delivery'])
            ->and($other->fresh()->only(['invoice_number', 'delivery_status']))->toBe(['invoice_number' => 'OTHER', 'delivery_status' => 'confirmed']);
    })->with(['a number' => ['INV-2026-01'], 'cleared' => ['']]);

    test('an invoice number over 255 characters or not text is refused without writing', function (mixed $invoice) {
        $order = attachedTripWriteOrder(createStoreUser(), ['delivery_status' => 'confirmed', 'invoice_number' => 'OLD-1']);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->put(route('trips.orders.update_invoice_number', tripWriteOrderContext($order)), ['invoice_number' => $invoice])->assertSessionHasErrors('invoice_number');

        expect($order->fresh()->only(['invoice_number', 'delivery_status']))->toBe(['invoice_number' => 'OLD-1', 'delivery_status' => 'confirmed']);
    })->with(['too long' => [str_repeat('x', 256)], 'not text' => [['bad']]]);

    test('marking an order delivered returns to the list, or to the map picker for return_to=map', function () {
        $order = attachedTripWriteOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
        $map = attachedTripWriteOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
        $other = attachedTripWriteOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->put(route('trips.orders.update_order_status_to_delivered', [...tripWriteOrderContext($order), 'page' => 3]))->assertRedirect(route('trips.orders', ['page' => 3]));
        $this->put(route('trips.orders.update_order_status_to_delivered', tripWriteOrderContext($map)), ['return_to' => 'map'])->assertRedirect(route('trips.create-from-map'));
        $this->put(route('trips.orders.update_order_status_to_delivered', tripWriteOrderContext($other)), ['return_to' => 'elsewhere'])->assertSessionHasErrors('return_to');

        expect($order->fresh()->delivery_status)->toBe('delivered')
            ->and($map->fresh()->delivery_status)->toBe('delivered')
            ->and($other->fresh()->delivery_status)->toBe('on_delivery');
    });

    test('the map picker\'s PUT with return_to in its query marks the order delivered and returns to the picker', function () {
        $order = attachedTripWriteOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->put(route('trips.orders.update_order_status_to_delivered', [...tripWriteOrderContext($order), 'return_to' => 'map']), [], inertiaHeaders())
            ->assertRedirect(route('trips.create-from-map'));

        expect($order->fresh()->delivery_status)->toBe('delivered');
    });

    test('a missing order is a 404 and the old order-id URIs are gone', function () {
        $order = attachedTripWriteOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->put(route('trips.orders.update_invoice_number', 999999), ['invoice_number' => 'X'])->assertNotFound();
        $this->put(route('trips.orders.update_order_status_to_delivered', 999999))->assertNotFound();
        $this->post('/temp/admin/trips/orders/update-invoice-number', ['order_id' => $order->id, 'invoice_number' => 'X'])->assertNotFound();
        $this->post('/temp/admin/trips/orders/update-order-status', ['order_id' => $order->id])->assertNotFound();
        $this->post(route('trips.orders.update_order_status_to_delivered', tripWriteOrderContext($order)))->assertMethodNotAllowed();

        expect($order->fresh()->only(['invoice_number', 'delivery_status']))->toBe(['invoice_number' => null, 'delivery_status' => 'on_delivery']);
    });

    test('every order action refuses callers and changes no order', function (string $caller) {
        $order = attachedTripWriteOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
        actingAsRefusedStoreCaller($caller);
        $before = tripOrderStates();

        assertStoreCallerRefused($this->post(route('trips.orders.bulk-change-delivery-status'), ['trip_id' => $order->checkpoints()->firstOrFail()->trip_id, 'id' => [$order->id], 'delivery_status' => 'confirmed']), $caller);
        assertStoreCallerRefused($this->put(route('trips.orders.update_invoice_number', tripWriteOrderContext($order)), ['invoice_number' => 'X']), $caller);
        assertStoreCallerRefused($this->put(route('trips.orders.update_order_status_to_delivered', tripWriteOrderContext($order))), $caller);

        expect(tripOrderStates())->toBe($before);
    })->with('refused store admin callers');

    test('each trip order route needs its own permission', function (string $method, string $route, string $permission) {
        $order = attachedTripWriteOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
        $url = in_array($route, ['trips.orders', 'trips.orders.bulk-change-delivery-status'], true) ? route($route) : route($route, tripWriteOrderContext($order));
        $payload = ['trip_id' => $order->checkpoints()->firstOrFail()->trip_id, 'id' => [$order->id], 'delivery_status' => 'confirmed', 'invoice_number' => 'X'];
        actingAsStoreUser(createStoreStaff(...array_values(array_diff(['view_all_trips', 'edit_trip', 'edit_order', 'update_order_delivery_status'], [$permission]))));

        $this->call($method, $url, $payload)->assertForbidden();
        expect($order->fresh()->only(['invoice_number', 'delivery_status']))->toBe(['invoice_number' => null, 'delivery_status' => 'on_delivery']);

        actingAsStoreUser(createStoreStaff($permission));
        expect($this->call($method, $url, $payload)->getStatusCode())->toBeIn([200, 302]);
    })->with([
        'list' => ['GET', 'trips.orders', 'view_all_trips'],
        'bulk status' => ['POST', 'trips.orders.bulk-change-delivery-status', 'edit_trip'],
        'invoice number' => ['PUT', 'trips.orders.update_invoice_number', 'edit_trip'],
        'delivered' => ['PUT', 'trips.orders.update_order_status_to_delivered', 'edit_trip'],
    ]);

    test('deleting a selection from the trip orders list returns there with its filters', function () {
        $customer = createStoreUser();
        $order = attachedTripWriteOrder($customer, ['delivery_status' => 'confirmed', 'payment_status' => 'unpaid']);
        $kept = attachedTripWriteOrder($customer, ['delivery_status' => 'confirmed', 'payment_status' => 'unpaid']);
        actingAsStoreUser(createStoreStaff('delete_order'));

        $this->post(route('bulk-order-delete', ['return_to' => 'trip_orders', 'search' => 'x']), ['id' => [$order->id]])
            ->assertRedirect(route('trips.orders', ['search' => 'x']));
        $this->post(route('bulk-order-delete', ['return_to' => 'elsewhere']), ['id' => [$kept->id]])->assertSessionHasErrors('return_to');

        expect(Order::find($order->id))->toBeNull()->and(Order::find($kept->id))->not->toBeNull();
    });
});
