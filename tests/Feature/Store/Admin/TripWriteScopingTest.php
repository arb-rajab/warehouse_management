<?php

use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Trip;
use App\Models\Truck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    Queue::fake();
    $this->travelTo(now()->startOfSecond());
});

function scopedWriteTrip(string $status = 'started'): Trip
{
    return Trip::create([
        'code' => 'D10-'.random_int(1, 99999999),
        'truck_id' => Truck::create(['name' => 'D10 truck', 'is_available' => false])->id,
        'driver_id' => createStoreUser('driver')->id,
        'due_date' => '2026-10-26',
        'status' => $status,
        'active' => $status !== 'closed',
        'closed_at' => $status === 'closed' ? now() : null,
    ])->fresh();
}

function scopedWriteStop(Trip $trip, ?Order $order = null): Checkpoint
{
    return $trip->checkpoints()->create([
        'relationable_type' => $order === null ? StoreUser::class : Order::class,
        'relationable_id' => $order?->id ?? createStoreUser()->id,
        'type' => $order === null ? 'other' : 'delivery',
        'status' => 'pending',
        'sort_order_in_trip' => 1,
        'notes' => 'Original',
    ])->fresh();
}

/** Every column, every row, including timestamps and unrelated noise. */
function scopedWriteSnapshot(): array
{
    $snapshot = [];
    foreach (['orders', 'order_details', 'checkpoints', 'trips', 'trucks', 'notifications', 'wms_jobs', 'wms_job_batches', 'wms_failed_jobs'] as $table) {
        $snapshot[$table] = DB::table($table)->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all();
    }

    return $snapshot;
}

function assertScopedWriteUnchanged(array $before): void
{
    expect(scopedWriteSnapshot())->toBe($before);
    Notification::assertNothingSent();
    Queue::assertNothingPushed();
}

test('D10 another trips order cannot be delivered or invoiced', function (string $action) {
    $trip = scopedWriteTrip();
    $own = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    scopedWriteStop($trip, $own);
    $foreign = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery', 'invoice_number' => 'OLD']);
    scopedWriteStop(scopedWriteTrip(), $foreign);
    actingAsStoreUser(createStoreStaff('edit_trip'));
    $before = scopedWriteSnapshot();

    $response = $this->putJson(route($action, $foreign), ['trip_id' => $trip->id, 'invoice_number' => 'WRONG']);

    assertScopedWriteUnchanged($before);
    $response->assertNotFound();
})->with(['trips.orders.update_order_status_to_delivered', 'trips.orders.update_invoice_number']);

test('D10 a checkpoint from another trip cannot change status or notes', function (string $action) {
    $trip = scopedWriteTrip();
    scopedWriteStop($trip);
    $foreign = scopedWriteStop(scopedWriteTrip());
    actingAsStoreUser(createStoreStaff('edit_trip'));
    $before = scopedWriteSnapshot();

    $response = $this->putJson(route($action, $foreign), ['trip_id' => $trip->id, 'status' => 'delivered', 'notes' => 'WRONG']);

    assertScopedWriteUnchanged($before);
    $response->assertNotFound();
})->with(['checkpoints.update-status', 'checkpoints.update-notes']);

test('D10 closed trip refuses order and checkpoint writes', function (string $action) {
    $trip = scopedWriteTrip('closed');
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    $checkpoint = scopedWriteStop($trip, $order);
    scopedWriteStop(scopedWriteTrip(), createStoreOrder(createStoreUser(), ['delivery_status' => 'confirmed']));
    actingAsStoreUser(createStoreStaff('edit_trip'));
    $before = scopedWriteSnapshot();
    $url = str_starts_with($action, 'checkpoints.') ? route($action, $checkpoint) : route($action, $order);
    $response = $this->putJson($url, ['trip_id' => $trip->id, 'invoice_number' => 'WRONG', 'status' => 'delivered', 'notes' => 'WRONG']);

    assertScopedWriteUnchanged($before);
    $response->assertUnprocessable()->assertJsonValidationErrors('trip_id');
    expect($response->json('errors.trip_id.0'))->toBe(__('messages.trip_closed_read_only'));
})->with(['trips.orders.update_order_status_to_delivered', 'trips.orders.update_invoice_number', 'checkpoints.update-status', 'checkpoints.update-notes']);

test('D10 pending delivered and cancelled orders may not be marked delivered', function (?string $status) {
    $trip = scopedWriteTrip();
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => $status]);
    scopedWriteStop($trip, $order);
    actingAsStoreUser(createStoreStaff('edit_trip'));
    $before = scopedWriteSnapshot();
    $this->travel(5)->seconds();

    $response = $this->putJson(route('trips.orders.update_order_status_to_delivered', $order), ['trip_id' => $trip->id]);

    assertScopedWriteUnchanged($before);
    $response->assertUnprocessable()->assertJsonValidationErrors('delivery_status');
})->with(['delivered', 'cancelled', 'pending', 'unknown', 'null status' => [null]]);

/** Existing permission guards apply to each of these actions. */
dataset('D10 writes', [
    'delivered' => ['PUT', 'trips.orders.update_order_status_to_delivered', 'order'],
    'invoice' => ['PUT', 'trips.orders.update_invoice_number', 'order'],
    'checkpoint status' => ['PUT', 'checkpoints.update-status', 'checkpoint'],
    'checkpoint notes' => ['PUT', 'checkpoints.update-notes', 'checkpoint'],
    'bulk status' => ['POST', 'trips.orders.bulk-change-delivery-status', 'bulk'],
]);

test('D10 every write retains its caller rejections with no side effects', function (string $method, string $action, string $child, string $caller) {
    $trip = scopedWriteTrip();
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    $checkpoint = scopedWriteStop($trip, $order);
    actingAsRefusedStoreCaller($caller);
    $before = scopedWriteSnapshot();
    $url = $child === 'bulk' ? route($action) : route($action, $child === 'order' ? $order : $checkpoint);
    $response = $this->call($method, $url, ['trip_id' => $trip->id, 'id' => [$order->id], 'invoice_number' => 'X', 'status' => 'delivered', 'notes' => 'X', 'delivery_status' => 'confirmed']);

    assertScopedWriteUnchanged($before);
    assertStoreCallerRefused($response, $caller);
})->with('D10 writes')->with('refused store admin callers');

test('D10 every write requires a real parent trip', function (string $method, string $action, string $child) {
    $trip = scopedWriteTrip();
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    $checkpoint = scopedWriteStop($trip, $order);
    actingAsStoreUser(createStoreStaff('edit_trip'));
    $before = scopedWriteSnapshot();
    $url = $child === 'bulk' ? route($action) : route($action, $child === 'order' ? $order : $checkpoint);
    $data = ['id' => [$order->id], 'invoice_number' => 'X', 'status' => 'delivered', 'notes' => 'X', 'delivery_status' => 'confirmed'];
    foreach ([null, 'bad', 0] as $id) {
        $this->json($method, $url, [...$data, 'trip_id' => $id])->assertUnprocessable()->assertJsonValidationErrors('trip_id');
        assertScopedWriteUnchanged($before);
    }
    $this->json($method, $url, [...$data, 'trip_id' => 999999])->assertNotFound();
    assertScopedWriteUnchanged($before);
})->with('D10 writes');

test('D10 missing child IDs remain 404', function (string $action) {
    $trip = scopedWriteTrip();
    scopedWriteStop($trip);
    actingAsStoreUser(createStoreStaff('edit_trip'));
    $before = scopedWriteSnapshot();

    $this->putJson(route($action, 999999), ['trip_id' => $trip->id, 'status' => 'delivered', 'notes' => 'X', 'invoice_number' => 'X'])->assertNotFound();
    assertScopedWriteUnchanged($before);
})->with(['trips.orders.update_order_status_to_delivered', 'trips.orders.update_invoice_number', 'checkpoints.update-status', 'checkpoints.update-notes']);

test('D10 unattached orders and customer checkpoints cannot authorize an order write', function (string $action) {
    $trip = scopedWriteTrip();
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    $trip->checkpoints()->create(['relationable_type' => StoreUser::class, 'relationable_id' => $order->id, 'type' => 'other', 'status' => 'pending', 'sort_order_in_trip' => 1]);
    actingAsStoreUser(createStoreStaff('edit_trip'));
    $before = scopedWriteSnapshot();

    $this->putJson(route($action, $order), ['trip_id' => $trip->id, 'invoice_number' => 'X'])->assertNotFound();
    assertScopedWriteUnchanged($before);
})->with(['trips.orders.update_order_status_to_delivered', 'trips.orders.update_invoice_number']);

test('D10 a bulk selection rolls back earlier orders when any attachment is foreign', function () {
    $trip = scopedWriteTrip();
    $own = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    $foreign = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    scopedWriteStop($trip, $own);
    scopedWriteStop(scopedWriteTrip(), $foreign);
    actingAsStoreUser(createStoreStaff('edit_trip'));
    $before = scopedWriteSnapshot();

    $this->postJson(route('trips.orders.bulk-change-delivery-status'), ['trip_id' => $trip->id, 'id' => [$own->id, $foreign->id], 'delivery_status' => 'confirmed'])
        ->assertUnprocessable()->assertJsonValidationErrors('id');
    assertScopedWriteUnchanged($before);
});

test('D10 closed trips refuse bulk writes too', function () {
    $trip = scopedWriteTrip('closed');
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'delivered']);
    scopedWriteStop($trip, $order);
    actingAsStoreUser(createStoreStaff('edit_trip'));
    $before = scopedWriteSnapshot();

    $this->postJson(route('trips.orders.bulk-change-delivery-status'), ['trip_id' => $trip->id, 'id' => [$order->id], 'delivery_status' => 'confirmed'])
        ->assertUnprocessable()->assertJsonValidationErrors('trip_id');
    assertScopedWriteUnchanged($before);
});

test('D10 valid delivery changes only its order delivery status', function (string $tripStatus, string $orderStatus) {
    $trip = scopedWriteTrip($tripStatus);
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => $orderStatus, 'payment_status' => 'paid', 'commission_calculated' => 1, 'invoice_number' => 'KEEP']);
    scopedWriteStop($trip, $order);
    scopedWriteStop(scopedWriteTrip(), createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']));
    actingAsStoreUser(createStoreStaff('edit_trip'));
    $expected = scopedWriteSnapshot();
    $expected['orders'][0]['delivery_status'] = 'delivered';

    $this->put(route('trips.orders.update_order_status_to_delivered', [$order, 'page' => 2]), ['trip_id' => $trip->id])->assertRedirect(route('trips.orders', ['page' => 2]));
    assertScopedWriteUnchanged($expected);
})->with(['pending', 'started'])->with(['confirmed', 'ready_for_delivery', 'on_delivery']);

test('D10 invoices preserve the legacy transition for attached orders in every status', function (string $status) {
    $trip = scopedWriteTrip();
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => $status, 'payment_status' => 'paid', 'commission_calculated' => 1]);
    scopedWriteStop($trip, $order);
    actingAsStoreUser(createStoreStaff('edit_trip'));
    $expected = scopedWriteSnapshot();
    $expected['orders'][0]['delivery_status'] = 'on_delivery';
    $expected['orders'][0]['invoice_number'] = 'INV-KEEP';

    $this->put(route('trips.orders.update_invoice_number', $order), ['trip_id' => $trip->id, 'invoice_number' => 'INV-KEEP'])->assertRedirect(route('trips.orders'));
    assertScopedWriteUnchanged($expected);
})->with(['pending', 'confirmed', 'ready_for_delivery', 'on_delivery', 'delivered', 'cancelled']);

test('D10 valid checkpoint writes preserve orders trips attachments and the first arrival', function () {
    $trip = scopedWriteTrip();
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'on_delivery']);
    $checkpoint = scopedWriteStop($trip, $order);
    $checkpoint->update(['status' => 'delivered', 'arrived_at' => '2026-10-20 10:00:00', 'cmr_file' => '17', 'photos' => '18']);
    scopedWriteStop(scopedWriteTrip());
    actingAsStoreUser(createStoreStaff('edit_trip'));
    $expected = scopedWriteSnapshot();
    $expected['checkpoints'][0]['status'] = 'has_issue';
    $expected['checkpoints'][0]['notes'] = null;

    $this->put(route('checkpoints.update-status', $checkpoint), ['trip_id' => $trip->id, 'status' => 'has_issue'])->assertRedirect(route('trips.show', $trip));
    $this->put(route('checkpoints.update-notes', $checkpoint), ['trip_id' => $trip->id, 'notes' => ''])->assertRedirect(route('trips.show', $trip));
    assertScopedWriteUnchanged($expected);
});

test('D10 closed trip detail and order reads remain available', function () {
    seedStoreCurrency();
    $trip = scopedWriteTrip('closed');
    $order = createStoreOrder(createStoreUser(), ['delivery_status' => 'delivered']);
    scopedWriteStop($trip, $order);
    actingAsStoreUser(createStoreStaff('view_all_trips'));
    $before = scopedWriteSnapshot();

    $this->get(route('trips.show', $trip))->assertOk();
    $this->get(route('trips.orders'))->assertOk();
    assertScopedWriteUnchanged($before);
});
