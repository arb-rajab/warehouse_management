<?php

namespace App\Http\Controllers\Store\Admin;

use App\Enums\CheckpointType;
use App\Enums\StoreTripList;
use App\Enums\TripStatus;
use App\Events\SetTruckAvailability;
use App\Http\Controllers\Concerns\ReservesTripTrucks;
use App\Http\Controllers\Concerns\WritesTripCheckpoints;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\FlashesSuccess;
use App\Http\Controllers\Store\Admin\Concerns\SearchesOptions;
use App\Http\Requests\Store\Admin\BulkDeleteTripsRequest;
use App\Http\Requests\Store\Admin\FilterTripsRequest;
use App\Http\Requests\Store\Admin\SearchOptionsRequest;
use App\Http\Requests\Store\Admin\StoreTripRequest;
use App\Http\Requests\Store\Admin\TripListActionRequest;
use App\Http\Requests\Store\Admin\UpdateTripNotesRequest;
use App\Http\Requests\Store\Admin\UpdateTripRequest;
use App\Http\Requests\Store\Admin\UpdateTripStatusRequest;
use App\Http\Resources\Store\TripFormResource;
use App\Http\Resources\Store\TripResource;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Trip;
use App\Models\Truck;
use App\Services\DriverService;
use App\Services\UserService;
use App\Support\NewestFirst;
use App\Support\SearchOption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The all-trips and today's-trips lists with their status, notes and delete
 * actions, and the trip create and edit forms. The trip orders list is
 * `TripOrderController` and the trip detail with its checkpoints
 * `TripCheckpointController`, the map picker `TripMapController`.
 */
class TripController extends Controller implements HasMiddleware
{
    use FlashesSuccess;
    use ReservesTripTrucks;
    use SearchesOptions;
    use WritesTripCheckpoints;

    public function __construct(private UserService $userService, private DriverService $driverService) {}

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_trips', only: ['index', 'today']),
            new Middleware('permission:add_trip', only: ['create', 'store']),
            new Middleware('permission:add_trip|edit_trip', only: ['orderOptions', 'customerOptions']),
            new Middleware('permission:edit_trip', only: ['edit', 'update', 'updateStatus', 'updateNotes']),
            new Middleware('permission:delete_trip', only: ['destroy', 'bulkDestroy']),
        ];
    }

    public function index(FilterTripsRequest $request): Response
    {
        return $this->renderList($request, StoreTripList::All);
    }

    public function today(FilterTripsRequest $request): Response
    {
        return $this->renderList($request, StoreTripList::Today);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Trips/Trips/Create');
    }

    /**
     * The legacy writer, unchanged: the departure checkpoint (store user 9)
     * first, then the posted rows; the truck is reserved in the same
     * transaction, the route sorted by the routing service when it answers
     * and the driver notified. Returns to the trip's detail.
     */
    public function store(StoreTripRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $trip = DB::transaction(function () use ($data) {
            $truck = $this->lockTruckForTrip((int) $data['truck_id']);
            $checkpoints = [];
            $this->applyDefaultFirstCheckpoint($checkpoints);
            $checkpoints = [...$checkpoints, ...$this->prepareCheckpointRows($data['checkpoints_array'], offset: 1)];
            $trip = $this->createTrip($data);
            $trip->checkpoints()->createMany($checkpoints);
            event(new SetTruckAvailability($trip, $truck, false));

            return $trip;
        });
        $this->sortBasedOnShortestPath($trip);
        $this->driverService->notifyDriver($trip);
        $this->flashSuccess('trip_has_been_created_successfully');

        return redirect()->route('trips.show', $trip->id);
    }

    public function edit(Trip $trip): Response
    {
        $trip->setRelation('checkpoints', $trip->checkpoints()
            ->select(['id', 'trip_id', 'relationable_type', 'relationable_id', 'type', 'notes', 'sort_order_in_trip'])
            // `with()` types its constraints as taking any relation;
            // `relationable` is always `Checkpoint`'s MorphTo.
            ->with(['relationable' => function (Relation $relation): void {
                if ($relation instanceof MorphTo) {
                    $relation->morphWith([Order::class => ['user:id,name', 'rep:id,name']]);
                    $relation->constrain([
                        StoreUser::class => fn ($query) => $query->select(['id', 'name']),
                        Order::class => fn ($query) => $query->select(['id', 'code', 'by_rep', 'user_id', 'for_customer']),
                    ]);
                }
            }])->get());

        return Inertia::render('Store/Admin/Trips/Trips/Edit', [
            'trip' => new TripFormResource($trip),
        ]);
    }

    /**
     * The legacy writer, unchanged: stored checkpoints keep their identity
     * (only their notes and position change), rows left out are deleted with
     * one query and new rows are added. A closed trip does not reserve its
     * truck again; a truck is freed only when no other open trip holds it,
     * the trip's old truck included. The driver is notified only when the
     * driver changes: the posted id is a string, so both sides are compared
     * as integers.
     */
    public function update(UpdateTripRequest $request, Trip $trip): RedirectResponse
    {
        $data = $request->validated();
        $driverChanged = false;
        $trip = DB::transaction(function () use ($data, $trip, &$driverChanged) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            $oldTruckId = $trip->truck_id;
            $truck = $this->lockTruckForTrip((int) $data['truck_id'], $trip, reserve: $trip->status !== TripStatus::Closed->value);
            $driverId = isset($data['driver_id']) ? (int) $data['driver_id'] : null;
            $driverChanged = ($trip->driver_id === null ? null : (int) $trip->driver_id) !== $driverId;
            $checkpoints = $this->prepareCheckpointRows($data['checkpoints_array'], $trip);
            $trip->fill([
                'driver_id' => $driverId,
                'truck_id' => $truck->id,
                'due_date' => $data['due_date'],
                'notes' => $data['notes'] ?? null,
            ])->save();
            $retained = array_filter(array_column($checkpoints, 'id'));
            $trip->checkpoints()->whereNotIn('id', $retained)->delete();
            foreach ($checkpoints as $checkpoint) {
                $id = $checkpoint['id'] ?? null;
                unset($checkpoint['id']);
                if ($id !== null) {
                    $trip->checkpoints()->whereKey($id)->update($checkpoint);
                } else {
                    $trip->checkpoints()->create($checkpoint);
                }
            }
            $available = $trip->status === TripStatus::Closed->value && ! $truck->trips()->where('status', '<>', TripStatus::Closed->value)->exists();
            event(new SetTruckAvailability($trip, $truck, $available));
            if ($oldTruckId !== null && $oldTruckId !== $truck->id) {
                $oldTruck = Truck::query()->lockForUpdate()->find($oldTruckId);
                if ($oldTruck !== null) {
                    $available = ! $oldTruck->trips()->where('status', '<>', TripStatus::Closed->value)->exists();
                    event(new SetTruckAvailability($trip, $oldTruck, $available));
                }
            }

            return $trip;
        });
        $this->sortBasedOnShortestPath($trip);
        if ($driverChanged) {
            $this->driverService->notifyDriver($trip);
        }
        $this->flashSuccess('trip_has_been_updated_successfully');

        return redirect()->route('trips.show', $trip->id);
    }

    /**
     * The legacy transition, unchanged: closing keeps the first close time,
     * marks the trip's orders delivered and frees the truck once no other
     * open trip holds it; starting or reopening reserves the truck again
     * (a `truck_id` error when another open trip holds it) and keeps the
     * first start time.
     */
    public function updateStatus(UpdateTripStatusRequest $request, Trip $trip): RedirectResponse
    {
        $status = TripStatus::from($request->string('status')->toString());
        DB::transaction(function () use ($trip, $status): void {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            $truck = $status === TripStatus::Closed
                ? Truck::query()->lockForUpdate()->findOrFail($trip->truck_id)
                : $this->lockTruckForTrip((int) $trip->truck_id, $trip);
            $trip->status = $status->value;
            $trip->active = $status !== TripStatus::Closed;
            $trip->closed_at = $status === TripStatus::Closed ? ($trip->closed_at ?? now()) : null;
            if ($status === TripStatus::Started) {
                $trip->started_at ??= now();
            }
            $trip->save();
            if ($status === TripStatus::Closed) {
                foreach ($trip->checkpoints()->select('relationable_id')->where('relationable_type', Order::class)->get() as $checkpoint) {
                    Order::query()->whereKey($checkpoint->relationable_id)->update(['delivery_status' => 'delivered']);
                }
            }
            $available = $status === TripStatus::Closed && ! $truck->trips()->where('status', '<>', TripStatus::Closed->value)->exists();
            event(new SetTruckAvailability($trip, $truck, $available));
        });
        $this->flashSuccess('trip_status_has_been_updated_successfully');

        return $this->redirectToList($request->tripList(), $request);
    }

    public function updateNotes(UpdateTripNotesRequest $request, Trip $trip): RedirectResponse
    {
        $trip->notes = $request->validated('notes');
        $trip->save();
        $this->flashSuccess('trip_notes_have_been_updated_successfully');

        return $this->redirectToList($request->tripList(), $request);
    }

    public function destroy(TripListActionRequest $request, Trip $trip): RedirectResponse
    {
        DB::transaction(function () use ($trip): void {
            $this->deleteTrip(Trip::query()->lockForUpdate()->findOrFail($trip->id));
        });
        $this->flashSuccess('trip_has_been_deleted_successfully');

        return $this->redirectToList($request->tripList(), $request);
    }

    public function bulkDestroy(BulkDeleteTripsRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = array_map('intval', $request->validated('id'));
        DB::transaction(function () use ($ids): void {
            Trip::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()
                ->each(fn (Trip $trip) => $this->deleteTrip($trip));
        });
        $this->flashSuccess('trips_have_been_deleted_successfully');

        return $this->redirectToList($request->tripList(), $request);
    }

    /**
     * The typeahead behind a delivery checkpoint's order (StoreSearchSelect):
     * the orders out for delivery, by code or customer name, newest code first.
     */
    public function orderOptions(SearchOptionsRequest $request): JsonResponse
    {
        $term = $this->searchTerm($request);
        $orders = Order::query()->select(['id', 'code', 'by_rep', 'user_id', 'for_customer', 'grand_total'])
            ->with(['user:id,name', 'rep:id,name'])->where('delivery_status', 'on_delivery');
        if ($term !== '') {
            $pattern = $this->likePattern($term);
            $orders->where(fn (Builder $match) => $match
                ->whereRaw('`code` like ? escape ?', [$pattern, '\\'])
                ->orWhereHas('user', fn (Builder $user) => $user->whereRaw('`name` like ? escape ?', [$pattern, '\\']))
                ->orWhereHas('rep', fn (Builder $rep) => $rep->whereRaw('`name` like ? escape ?', [$pattern, '\\'])));
        }
        $this->orderByRelevance($orders, 'code', $term, 'desc');

        return $this->optionsPage($orders, fn (Order $order): array => SearchOption::tripOrder($order));
    }

    /**
     * The typeahead behind a customer checkpoint's customer (StoreSearchSelect):
     * every store user who is not a representative.
     */
    public function customerOptions(SearchOptionsRequest $request): JsonResponse
    {
        $term = $this->searchTerm($request);
        $customers = StoreUser::query()->select(['id', 'name', 'email', 'phone', 'AccSysID', 'member_serial', 'rep_discount_percentage'])->where('is_rep', false);
        if ($term !== '') {
            $this->whereWordsMatch($customers, ['name', 'email', 'phone', 'AccSysID', 'member_serial'], $term);
        }
        $this->orderByRelevance($customers, 'name', $term);

        return $this->optionsPage($customers, fn (StoreUser $user): array => SearchOption::customer($user));
    }

    /**
     * The posted rows in their posted order. A stored row keeps its identity
     * and only takes its new position and notes; a new row is resolved to
     * its order or customer with the address and coordinates of the moment.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function prepareCheckpointRows(array $rows, ?Trip $trip = null, int $offset = 0): array
    {
        $checkpoints = [];
        foreach (array_values($rows) as $index => $row) {
            $id = (int) ($row['id'] ?? 0);
            $type = $row['checkpoint_type'] === CheckpointType::Delivery->value ? Order::class : StoreUser::class;
            if ($id > 0) {
                $checkpoint = $trip?->checkpoints()->lockForUpdate()->find($id);
                if ($checkpoint === null || $checkpoint->relationable_type !== $type || $checkpoint->relationable_id !== (int) $row['checkpoint_related_id'] || $checkpoint->type !== $row['checkpoint_type']) {
                    throw ValidationException::withMessages(['checkpoints_array' => __('validation.exists', ['attribute' => __('validation.attributes.checkpoint')])]);
                }
                $checkpoints[] = ['id' => $id, 'sort_order_in_trip' => $index + $offset + 1, 'notes' => $row['checkpoint_notes'] ?? ''];

                continue;
            }
            if ($type === Order::class) {
                $order = Order::query()->findOrFail((int) $row['checkpoint_related_id']);
                $address = json_decode($order->shipping_address);
                $coordinates = $this->userService->getUserLongLat($order->customer, $order);
            } else {
                $customer = StoreUser::query()->findOrFail((int) $row['checkpoint_related_id']);
                $address = formateAddressArray($customer->addresses()->where('set_default', true)->first());
                $coordinates = $this->userService->getUserLongLat($customer);
            }
            $checkpoints[] = $this->formatCheckpointsArray($row, $address, $index + $offset, $type, $coordinates);
        }

        return $checkpoints;
    }

    private function renderList(FilterTripsRequest $request, StoreTripList $list): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');
        /** @var list<string> $statuses */
        $statuses = $request->validated('status') ?? [];
        $trips = Trip::query()
            ->select(['id', 'code', 'driver_id', 'truck_id', 'due_date', 'status', 'started_at', 'closed_at', 'distance', 'duration', 'notes'])
            ->with(['driver:id,name', 'truck:id,name']);
        if ($list === StoreTripList::Today) {
            $trips->whereDate('due_date', today());
        }
        if (is_string($search) && $search !== '') {
            $trips->search($search);
        }
        if ($statuses !== []) {
            $trips->whereIn('status', $statuses);
        }

        return Inertia::render('Store/Admin/Trips/Trips/Index', [
            'listType' => $list->value,
            'trips' => $this->paginated(TripResource::collection(
                $trips->tap(NewestFirst::apply(...))->paginate($perPage)->withQueryString()
            )),
            'filters' => ['per_page' => $perPage, 'search' => $search ?? '', 'status' => $statuses],
            'statuses' => array_column(TripStatus::cases(), 'value'),
        ]);
    }

    /**
     * Deletes the trip and its checkpoints with one query each, as the legacy
     * handlers did (no checkpoint `deleting` hook runs, so the orders keep
     * their delivery status), then frees the truck unless another open trip
     * still holds it. The legacy single delete freed it unconditionally and
     * the legacy bulk delete never did.
     */
    private function deleteTrip(Trip $trip): void
    {
        $trip->checkpoints()->delete();
        $trip->delete();
        $truck = $trip->truck_id === null ? null : Truck::query()->lockForUpdate()->find($trip->truck_id);
        if ($truck !== null) {
            event(new SetTruckAvailability($trip, $truck, ! $truck->trips()->where('status', '<>', TripStatus::Closed->value)->exists()));
        }
    }

    private function redirectToList(StoreTripList $list, Request $request): RedirectResponse
    {
        $route = match ($list) {
            StoreTripList::All => 'trips.index',
            StoreTripList::Today => 'trips.today-trips',
            StoreTripList::Map => 'trips.create-from-map',
        };

        return redirect()->route($route, Arr::except($request->query(), ['list']));
    }
}
