<?php

namespace App\Http\Controllers\Store\Admin;

use App\Enums\CheckpointStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\UpdateCheckpointNotesRequest;
use App\Http\Requests\Store\Admin\UpdateCheckpointStatusRequest;
use App\Http\Resources\Store\TripCheckpointResource;
use App\Http\Resources\Store\TripResource;
use App\Http\Resources\Store\TruckPhotoResource;
use App\Http\Resources\Store\UploadResource;
use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Trip;
use App\Models\Upload;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A trip's detail: its checkpoints in their order, with the inline notes and
 * status writes and the CMR download. The Blade page's Leaflet map is not
 * carried over (the map dependency's approval is unverified); the retained
 * map picker stays on the root `Trip\TripController`.
 */
class TripCheckpointController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_trips', only: ['show', 'printCmr']),
            new Middleware('permission:edit_trip', only: ['updateNotes', 'updateStatus']),
        ];
    }

    public function show(Trip $trip): Response
    {
        $trip->load(['driver:id,name', 'truck:id,name']);
        $customer = fn ($query) => $query->select(['id', 'name', 'phone', 'company_name', 'shipping_address']);
        $checkpoints = $trip->checkpoints()
            ->select(['id', 'trip_id', 'relationable_type', 'relationable_id', 'type', 'status', 'arrived_at', 'sort_order_in_trip', 'eta', 'notes', 'cmr_file', 'photos'])
            ->with(['relationable' => function (MorphTo $relation) use ($customer): void {
                $relation->morphWith([Order::class => ['user' => $customer, 'rep' => $customer]]);
                $relation->morphWithCount([Order::class => ['orderDetails']]);
                $relation->constrain([
                    StoreUser::class => $customer,
                    Order::class => fn ($query) => $query->select(['id', 'by_rep', 'for_customer', 'user_id', 'code', 'grand_total', 'invoice_number']),
                ]);
            }])
            ->orderBy('sort_order_in_trip')->orderBy('id')->get();
        $photoIds = array_values($checkpoints
            ->flatMap(fn (Checkpoint $checkpoint) => TripCheckpointResource::uploadIds($checkpoint->photos))
            ->unique()->all());

        return Inertia::render('Store/Admin/Trips/Trips/Show', [
            'trip' => new TripResource($trip),
            'checkpoints' => TripCheckpointResource::collection($checkpoints),
            'photos' => TruckPhotoResource::collection(Upload::query()
                ->select(['id', 'file_original_name', 'file_name', 'external_link', 'type', 'file_size'])
                ->whereIn('id', $photoIds)->orderBy('id')->get()),
            'statuses' => array_column(CheckpointStatus::cases(), 'value'),
        ]);
    }

    public function updateNotes(UpdateCheckpointNotesRequest $request, Checkpoint $checkpoint): RedirectResponse
    {
        $checkpoint->notes = $request->validated('notes');
        $checkpoint->save();

        return $this->redirectToTrip($checkpoint, $request);
    }

    /**
     * The legacy transition, unchanged: the checkpoint is locked and takes
     * the status; a delivered checkpoint keeps its first arrival time.
     */
    public function updateStatus(UpdateCheckpointStatusRequest $request, Checkpoint $checkpoint): RedirectResponse
    {
        $status = CheckpointStatus::from($request->string('status')->toString());
        DB::transaction(function () use ($checkpoint, $status): void {
            $checkpoint = Checkpoint::query()->lockForUpdate()->findOrFail($checkpoint->id);
            $checkpoint->status = $status->value;
            if ($status === CheckpointStatus::Delivered) {
                $checkpoint->arrived_at ??= now();
            }
            $checkpoint->save();
        });

        return $this->redirectToTrip($checkpoint, $request);
    }

    /**
     * Sends the browser to the checkpoint's CMR file, as before, but only to
     * a managed local file or an HTTP(S) URL (the upload library's own rule,
     * `UploadResource`). A checkpoint without one returns to its trip with a
     * `cmr` error.
     */
    public function printCmr(Checkpoint $checkpoint, Request $request): RedirectResponse
    {
        $uploadId = TripCheckpointResource::uploadIds($checkpoint->cmr_file)[0] ?? null;
        $upload = $uploadId === null ? null : Upload::query()
            ->select(['id', 'file_original_name', 'file_name', 'external_link', 'type', 'file_size'])
            ->find($uploadId);
        $url = $upload === null ? null : (new UploadResource($upload))->toArray($request)['url'];

        if ($url === null) {
            return $this->redirectToTrip($checkpoint, $request)->withErrors(['cmr' => __('messages.trip_cmr_unavailable')]);
        }

        return redirect()->away($url);
    }

    private function redirectToTrip(Checkpoint $checkpoint, Request $request): RedirectResponse
    {
        return redirect()->route('trips.show', ['trip' => $checkpoint->trip_id, ...$request->query()]);
    }
}
