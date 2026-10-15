<?php

namespace App\Http\Controllers\Store\Admin;

use App\Enums\StoreTripList;
use App\Enums\TripStatus;
use App\Events\SetTruckAvailability;
use App\Http\Controllers\Concerns\ReservesTripTrucks;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\BulkDeleteTripsRequest;
use App\Http\Requests\Store\Admin\FilterTripsRequest;
use App\Http\Requests\Store\Admin\TripListActionRequest;
use App\Http\Requests\Store\Admin\UpdateTripNotesRequest;
use App\Http\Requests\Store\Admin\UpdateTripStatusRequest;
use App\Http\Resources\Store\TripResource;
use App\Models\Order;
use App\Models\Trip;
use App\Models\Truck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The all-trips and today's-trips lists with their status, notes and delete
 * actions. The trip forms, checkpoints, map picker, trip orders and CMR
 * download stay Blade on the root `Trip\TripController`.
 */
class TripController extends Controller implements HasMiddleware
{
    use ReservesTripTrucks;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_trips', only: ['index', 'today']),
            new Middleware('permission:edit_trip', only: ['updateStatus', 'updateNotes']),
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

        return $this->redirectToList($request->tripList(), $request);
    }

    public function updateNotes(UpdateTripNotesRequest $request, Trip $trip): RedirectResponse
    {
        $trip->notes = $request->validated('notes');
        $trip->save();

        return $this->redirectToList($request->tripList(), $request);
    }

    public function destroy(TripListActionRequest $request, Trip $trip): RedirectResponse
    {
        DB::transaction(function () use ($trip): void {
            $this->deleteTrip(Trip::query()->lockForUpdate()->findOrFail($trip->id));
        });

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

        return $this->redirectToList($request->tripList(), $request);
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
                $trips->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString()
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
