<?php

use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\StoreUser;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\Upload;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

// Each order checkpoint prints its grand total through single_price(), which
// reads the default currency; the page must never call the routing service.
beforeEach(function () {
    seedStoreCurrency();
    Http::fake();
    config(['store.asset_base_url' => null]);
});

function checkpointDetailTrip(array $attributes = []): Trip
{
    $truck = Truck::create(['name' => 'Detail truck', 'is_available' => false]);

    return Trip::create(['code' => 'Trip-'.random_int(1, 99999999), 'truck_id' => $truck->id, 'due_date' => '2026-10-10', 'status' => 'pending', 'active' => true, ...$attributes])->fresh();
}

function checkpointDetailStop(Trip $trip, array $attributes = []): Checkpoint
{
    return $trip->checkpoints()->create([
        'relationable_type' => StoreUser::class,
        'relationable_id' => createStoreUser()->id,
        'type' => 'other',
        'status' => 'pending',
        'sort_order_in_trip' => 1,
        ...$attributes,
    ])->fresh();
}

/**
 * Every checkpoint's writable columns, keyed by id.
 *
 * @return array<int, array<string, mixed>>
 */
function checkpointDetailStates(): array
{
    return Checkpoint::query()->orderBy('id')->get()
        ->mapWithKeys(fn (Checkpoint $checkpoint) => [$checkpoint->id => [
            'status' => $checkpoint->status,
            'arrived_at' => $checkpoint->arrived_at?->toDateTimeString(),
            'notes' => $checkpoint->notes,
            'cmr_file' => $checkpoint->cmr_file,
            'photos' => $checkpoint->photos,
        ]])->all();
}

describe('trip detail', function () {
    test('the detail renders the trip and every checkpoint field in stop order', function () {
        $driver = createStoreUser('driver');
        $driver->forceFill(['name' => 'Sam Driver'])->save();
        $trip = checkpointDetailTrip(['driver_id' => $driver->id, 'status' => 'started', 'started_at' => '2026-10-10 09:05:00', 'distance' => 12345.67, 'duration' => 3720, 'notes' => 'Trip note']);
        $customer = createStoreUser();
        $customer->forceFill(['name' => 'Alma Customer', 'phone' => '0611111111', 'company_name' => 'Alma BV', 'shipping_address' => 'Main street 1'])->save();
        $rep = createStoreUser();
        $rep->forceFill(['name' => 'Rita Rep'])->save();
        $order = createStoreOrder($rep, ['code' => '20261016-0001', 'by_rep' => 1, 'for_customer' => $customer->id, 'grand_total' => 125.5, 'invoice_number' => 'INV-7', 'delivery_status' => 'on_delivery']);
        $product = createStoreProduct();
        foreach ([1, 2] as $quantity) {
            OrderDetail::forceCreate(['order_id' => $order->id, 'seller_id' => $product->user_id, 'product_id' => $product->id, 'price' => 5, 'tax' => 0, 'quantity' => $quantity, 'payment_status' => 'unpaid']);
        }
        orderStoreProduct($customer, $product);
        $photo = Upload::factory()->create(['file_original_name' => 'door.jpg', 'type' => 'image', 'external_link' => 'https://cdn.example.com/door.jpg']);
        $second = Upload::factory()->create(['file_original_name' => 'box.jpg', 'type' => 'image', 'external_link' => 'https://cdn.example.com/box.jpg']);
        Upload::factory()->create(['file_original_name' => 'noise.jpg', 'type' => 'image']);
        $visit = createStoreUser();
        $visit->forceFill(['name' => 'Visit Customer', 'phone' => '0622222222', 'company_name' => null, 'shipping_address' => null])->save();
        $later = checkpointDetailStop($trip, ['relationable_id' => $visit->id, 'sort_order_in_trip' => 2, 'notes' => 'Ring twice']);
        $delivery = checkpointDetailStop($trip, [
            'relationable_type' => Order::class, 'relationable_id' => $order->id, 'type' => 'delivery',
            'status' => 'delivered', 'arrived_at' => '2026-10-10 10:30:00', 'eta' => 1830.4,
            'cmr_file' => (string) $photo->id, 'photos' => "{$photo->id},{$second->id},x",
        ]);
        checkpointDetailStop(checkpointDetailTrip(), ['notes' => 'Another trip']);
        actingAsStoreUser(createStoreStaff('view_all_trips'));

        $this->get(route('trips.show', $trip))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/Admin/Trips/Trips/Show')
            ->has('trip', fn (Assert $row) => $row
                ->where('id', $trip->id)->where('code', $trip->code)->where('status', 'started')
                ->where('due_date', '2026-10-10')->where('started_at', '2026-10-10T09:05:00+00:00')->where('closed_at', null)
                ->where('distance', 12345.67)->where('duration', 3720)->where('notes', 'Trip note')
                ->where('driver', ['id' => $driver->id, 'name' => 'Sam Driver'])
                ->where('truck', ['id' => $trip->truck_id, 'name' => 'Detail truck']))
            ->has('checkpoints', 2)
            ->has('checkpoints.0', fn (Assert $row) => $row
                ->where('id', $delivery->id)->where('position', 1)->where('type', 'delivery')
                ->where('status', 'delivered')->where('arrived_at', '2026-10-10T10:30:00+00:00')
                ->where('eta', 1830)->where('notes', null)
                ->where('order', ['id' => $order->id, 'code' => '20261016-0001', 'invoice_number' => 'INV-7', 'products' => 2, 'amount' => single_price(125.5)])
                ->where('customer', ['id' => $customer->id, 'name' => 'Alma Customer', 'phone' => '0611111111', 'company_name' => 'Alma BV', 'address' => 'Main street 1'])
                ->where('representative', ['id' => $rep->id, 'name' => 'Rita Rep'])
                ->where('has_cmr', true)
                ->where('photos', [$photo->id, $second->id]))
            ->has('checkpoints.1', fn (Assert $row) => $row
                ->where('id', $later->id)->where('position', 2)->where('type', 'other')
                ->where('status', 'pending')->where('arrived_at', null)->where('eta', null)->where('notes', 'Ring twice')
                ->where('order', null)
                ->where('customer', ['id' => $visit->id, 'name' => 'Visit Customer', 'phone' => '0622222222', 'company_name' => null, 'address' => null])
                ->where('representative', null)->where('has_cmr', false)->where('photos', []))
            ->where('photos', [
                ['id' => $photo->id, 'name' => 'door.jpg', 'url' => 'https://cdn.example.com/door.jpg'],
                ['id' => $second->id, 'name' => 'box.jpg', 'url' => 'https://cdn.example.com/box.jpg'],
            ])
            ->where('statuses', ['pending', 'delivered', 'has_issue']));

        Http::assertNothingSent();
    });

    test('a trip without checkpoints or a driver renders empty and a deleted order leaves its row blank', function () {
        $trip = checkpointDetailTrip();
        actingAsStoreUser(createStoreStaff('view_all_trips'));

        $this->get(route('trips.show', $trip))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Store/Admin/Trips/Trips/Show')
            ->where('trip.driver', null)->where('checkpoints', [])->where('photos', []));

        checkpointDetailStop($trip, ['relationable_type' => Order::class, 'relationable_id' => 999999, 'type' => 'delivery']);
        $this->get(route('trips.show', $trip))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('checkpoints.0.order', null)->where('checkpoints.0.customer', null)->where('checkpoints.0.representative', null));

        Http::assertNothingSent();
    });

    test('a missing trip is a 404', function () {
        actingAsStoreUser(createStoreStaff('view_all_trips'));

        $this->get(route('trips.show', ['trip' => 999999]))->assertNotFound();
    });

    test('the detail refuses unauthorized callers', function (string $caller) {
        $trip = checkpointDetailTrip();
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get(route('trips.show', $trip)), $caller);
    })->with('refused store admin callers');

    test('each trip detail route needs its own permission', function (string $method, string $route, string $permission) {
        $trip = checkpointDetailTrip();
        $checkpoint = checkpointDetailStop($trip);
        $url = $route === 'trips.show' ? route($route, $trip) : route($route, $checkpoint);
        $payload = ['notes' => 'Changed', 'status' => 'delivered'];
        actingAsStoreUser(createStoreStaff(...array_values(array_diff(['view_all_trips', 'edit_trip', 'add_trip', 'delete_trip'], [$permission]))));
        $before = checkpointDetailStates();

        $this->call($method, $url, $payload)->assertForbidden();
        expect(checkpointDetailStates())->toBe($before);

        actingAsStoreUser(createStoreStaff($permission));
        expect($this->call($method, $url, $payload)->getStatusCode())->toBeIn([200, 302]);
    })->with([
        'detail' => ['GET', 'trips.show', 'view_all_trips'],
        'notes' => ['PUT', 'checkpoints.update-notes', 'edit_trip'],
        'status' => ['PUT', 'checkpoints.update-status', 'edit_trip'],
        'cmr' => ['GET', 'checkpoints.print-cmr', 'view_all_trips'],
    ]);
});

describe('checkpoint notes', function () {
    test('notes save and return to the trip detail with its query', function () {
        $trip = checkpointDetailTrip();
        $checkpoint = checkpointDetailStop($trip, ['notes' => 'Old', 'status' => 'delivered', 'cmr_file' => '17', 'photos' => '18']);
        $other = checkpointDetailStop(checkpointDetailTrip(), ['notes' => 'Untouched']);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->put(route('checkpoints.update-notes', [$checkpoint, 'tab' => 'stops']), ['notes' => 'Leave at the back door'])
            ->assertRedirect(route('trips.show', [$trip, 'tab' => 'stops']));
        expect($checkpoint->fresh()->only(['notes', 'status', 'cmr_file', 'photos']))->toBe(['notes' => 'Leave at the back door', 'status' => 'delivered', 'cmr_file' => '17', 'photos' => '18'])
            ->and($other->fresh()->notes)->toBe('Untouched');

        $this->put(route('checkpoints.update-notes', $checkpoint), ['notes' => ''])->assertRedirect(route('trips.show', $trip));
        expect($checkpoint->fresh()->notes)->toBeNull();
    });

    test('notes follow the trip form\'s checkpoint notes rule and need an existing checkpoint', function () {
        $checkpoint = checkpointDetailStop(checkpointDetailTrip(), ['notes' => 'Old']);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->put(route('checkpoints.update-notes', $checkpoint), ['notes' => str_repeat('a', 1001)])->assertSessionHasErrors('notes');
        $this->put(route('checkpoints.update-notes', $checkpoint), ['notes' => ['array']])->assertSessionHasErrors('notes');
        $this->put(route('checkpoints.update-notes', ['checkpoint' => 999999]), ['notes' => 'x'])->assertNotFound();
        $this->post('/temp/admin/trips/update-checkpoint-notes', ['checkpoint_id' => $checkpoint->id, 'notes' => 'x'])->assertMethodNotAllowed();
        expect($checkpoint->fresh()->notes)->toBe('Old');

        $this->put(route('checkpoints.update-notes', $checkpoint), ['notes' => str_repeat('a', 1000)])->assertRedirect();
        expect($checkpoint->fresh()->notes)->toBe(str_repeat('a', 1000));
    });

    test('notes refuse callers and change nothing', function (string $caller) {
        $checkpoint = checkpointDetailStop(checkpointDetailTrip(), ['notes' => 'Old']);
        actingAsRefusedStoreCaller($caller);
        $before = checkpointDetailStates();

        assertStoreCallerRefused($this->put(route('checkpoints.update-notes', $checkpoint), ['notes' => 'Changed']), $caller);

        expect(checkpointDetailStates())->toBe($before);
    })->with('refused store admin callers');
});

describe('checkpoint status', function () {
    test('checkpoint statuses set arrival only when delivered and return to the detail', function (string $status) {
        $trip = checkpointDetailTrip();
        $checkpoint = checkpointDetailStop($trip);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->put(route('checkpoints.update-status', [$checkpoint, 'tab' => 'stops']), ['status' => $status])
            ->assertRedirect(route('trips.show', [$trip, 'tab' => 'stops']));

        expect($checkpoint->fresh()->status)->toBe($status);
        if ($status === 'delivered') {
            expect($checkpoint->fresh()->arrived_at)->not->toBeNull();
        } else {
            expect($checkpoint->fresh()->arrived_at)->toBeNull();
        }
        expect($trip->fresh()->status)->toBe('pending');
    })->with(['pending', 'has_issue', 'delivered']);

    test('delivering again keeps the first arrival and leaving delivered keeps it too', function () {
        $checkpoint = checkpointDetailStop(checkpointDetailTrip(), ['status' => 'delivered', 'arrived_at' => '2026-10-09 10:00:00']);
        actingAsStoreUser(createStoreStaff('edit_trip'));

        $this->put(route('checkpoints.update-status', $checkpoint), ['status' => 'delivered'])->assertRedirect();
        expect($checkpoint->fresh()->arrived_at->toDateTimeString())->toBe('2026-10-09 10:00:00');
        $this->put(route('checkpoints.update-status', $checkpoint), ['status' => 'has_issue'])->assertRedirect();
        expect($checkpoint->fresh()->status)->toBe('has_issue')->and($checkpoint->fresh()->arrived_at->toDateTimeString())->toBe('2026-10-09 10:00:00');
    });

    test('checkpoint status transitions refuse callers and preserve checkpoint identity and attachments', function (string $caller) {
        $checkpoint = checkpointDetailStop(checkpointDetailTrip(), ['cmr_file' => '17', 'photos' => '18']);
        actingAsRefusedStoreCaller($caller);
        $before = checkpointDetailStates();

        assertStoreCallerRefused($this->put(route('checkpoints.update-status', $checkpoint), ['status' => 'delivered']), $caller);

        expect(checkpointDetailStates())->toBe($before);
    })->with('refused store admin callers');

    test('checkpoint status validation rejects invalid enums, missing checkpoints and the old URI without writes', function () {
        $checkpoint = checkpointDetailStop(checkpointDetailTrip());
        actingAsStoreUser(createStoreStaff('edit_trip'));
        $before = checkpointDetailStates();

        $this->put(route('checkpoints.update-status', $checkpoint), ['status' => 'closed'])->assertSessionHasErrors('status');
        $this->put(route('checkpoints.update-status', $checkpoint), [])->assertSessionHasErrors('status');
        $this->put(route('checkpoints.update-status', ['checkpoint' => 999999]), ['status' => 'delivered'])->assertNotFound();
        $this->post('/temp/admin/trips/update-checkpoint-status', ['id' => $checkpoint->id, 'status' => 'delivered'])->assertMethodNotAllowed();

        expect(checkpointDetailStates())->toBe($before);
    });
});

describe('CMR download', function () {
    test('an HTTPS CMR file is opened as before', function () {
        $upload = Upload::factory()->create(['file_original_name' => 'cmr', 'type' => 'document', 'external_link' => 'https://cdn.example.com/cmr.pdf']);
        $checkpoint = checkpointDetailStop(checkpointDetailTrip(), ['cmr_file' => (string) $upload->id]);
        actingAsStoreUser(createStoreStaff('view_all_trips'));

        $this->get(route('checkpoints.print-cmr', $checkpoint))->assertRedirect('https://cdn.example.com/cmr.pdf');
    });

    test('a managed local CMR file is opened from the public uploads', function () {
        withScratchPublicPath(function (string $publicPath) {
            file_put_contents("$publicPath/uploads/all/cmr.pdf", '%PDF-1.4');
            $upload = Upload::factory()->create(['file_original_name' => 'cmr', 'type' => 'document', 'external_link' => null, 'file_name' => 'uploads/all/cmr.pdf']);
            $checkpoint = checkpointDetailStop(checkpointDetailTrip(), ['cmr_file' => "{$upload->id},999999"]);
            actingAsStoreUser(createStoreStaff('view_all_trips'));

            $this->get(route('checkpoints.print-cmr', $checkpoint))->assertRedirect(asset('uploads/all/cmr.pdf'));
        });
    });

    test('a checkpoint without a usable CMR file returns to its trip with an error', function (Closure $cmrFile) {
        $trip = checkpointDetailTrip();
        $checkpoint = checkpointDetailStop($trip, ['cmr_file' => $cmrFile()]);
        actingAsStoreUser(createStoreStaff('view_all_trips'));

        $this->get(route('checkpoints.print-cmr', [$checkpoint, 'tab' => 'stops']))
            ->assertRedirect(route('trips.show', [$trip, 'tab' => 'stops']))
            ->assertSessionHasErrors(['cmr' => __('messages.trip_cmr_unavailable')]);
    })->with([
        'no file' => [fn () => null],
        'not an id' => [fn () => 'cmr.pdf'],
        'a deleted upload' => [fn () => '999999'],
        'a script link' => [fn () => (string) Upload::factory()->create(['type' => 'document', 'external_link' => 'javascript:alert(1)'])->id],
        'a file outside the uploads' => [fn () => (string) Upload::factory()->create(['type' => 'document', 'external_link' => null, 'file_name' => '../.env'])->id],
    ]);

    test('the CMR download needs an existing checkpoint and refuses callers', function (string $caller) {
        $upload = Upload::factory()->create(['type' => 'document', 'external_link' => 'https://cdn.example.com/cmr.pdf']);
        $checkpoint = checkpointDetailStop(checkpointDetailTrip(), ['cmr_file' => (string) $upload->id]);
        actingAsRefusedStoreCaller($caller);

        assertStoreCallerRefused($this->get(route('checkpoints.print-cmr', $checkpoint)), $caller);
    })->with('refused store admin callers');

    test('an unknown checkpoint is a 404', function () {
        actingAsStoreUser(createStoreStaff('view_all_trips'));

        $this->get(route('checkpoints.print-cmr', ['checkpoint' => 999999]))->assertNotFound();
    });
});
