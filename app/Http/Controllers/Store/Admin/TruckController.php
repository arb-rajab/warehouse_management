<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\BuildsQrLabels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterTruckPhotosRequest;
use App\Http\Requests\Store\Admin\FilterTrucksRequest;
use App\Http\Requests\Store\Admin\StoreTruckRequest;
use App\Http\Requests\Store\Admin\UpdateTruckRequest;
use App\Http\Resources\Store\TruckPhotoResource;
use App\Http\Resources\Store\TruckResource;
use App\Models\Trip;
use App\Models\Truck;
use App\Models\Upload;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class TruckController extends Controller implements HasMiddleware
{
    use BuildsQrLabels;

    private const array COLUMNS = ['id', 'name', 'model', 'max_pallets_number', 'description', 'license_plate', 'photos'];

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_trucks', only: ['index', 'qr']),
            new Middleware('permission:add_truck', only: ['create', 'store']),
            new Middleware('permission:edit_truck', only: ['edit', 'update']),
            new Middleware('permission:add_truck|edit_truck', only: ['photos']),
        ];
    }

    public function index(FilterTrucksRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');
        $trucks = $this->truckQuery()->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->where('name', 'like', '%'.$search.'%')->orWhere('license_plate', 'like', '%'.$search.'%')))
            ->orderBy('id')->paginate($perPage)->withQueryString();

        $photoIds = array_values($trucks->getCollection()->flatMap(fn (Truck $truck) => explode(',', $truck->photos ?? ''))->filter()->unique()->all());

        return Inertia::render('Store/Admin/Trips/Trucks/Index', [
            'trucks' => $this->paginated(TruckResource::collection($trucks)),
            'photos' => TruckPhotoResource::collection($this->photoQuery($photoIds)->whereIn('id', $photoIds)->get()),
            'filters' => ['per_page' => $perPage, 'search' => $search ?? ''],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Trips/Trucks/Create', ['photos' => TruckPhotoResource::collection($this->photoQuery()->orderByDesc('id')->limit(20)->get())]);
    }

    public function edit(Truck $truck): Response
    {
        $truck = $this->truckQuery()->findOrFail($truck->id);
        $photoIds = explode(',', $truck->photos ?? '');

        return Inertia::render('Store/Admin/Trips/Trucks/Edit', [
            'truck' => new TruckResource($truck),
            'photos' => TruckPhotoResource::collection($this->photoQuery($photoIds)->whereIn('id', $photoIds)->get()),
        ]);
    }

    public function store(StoreTruckRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['photos'] = implode(',', $data['photos'] ?? []);
        Truck::query()->create($data);

        return redirect()->route('trucks.index');
    }

    public function update(UpdateTruckRequest $request, Truck $truck): RedirectResponse
    {
        $data = $request->validated();
        $data['photos'] = implode(',', $data['photos'] ?? []);
        $truck->update($data);

        return redirect()->route('trucks.index');
    }

    public function photos(FilterTruckPhotosRequest $request): JsonResponse
    {
        $images = $this->photoQuery()->when($request->filled('search'), fn (Builder $query) => $query->originalNameContains($request->string('search')->toString()))
            ->orderByDesc('id')->paginate(20);

        return response()->json(['data' => TruckPhotoResource::collection($images->items()), 'has_more' => $images->hasMorePages()]);
    }

    public function qr(Truck $truck): HttpResponse
    {
        abort_if(blank($truck->license_plate), 404);

        $svg = $this->qrLabelImage($truck->license_plate, $truck->license_plate, null, 'ltr', 600, 680);

        return Pdf::loadView('pdf.qr-labels', [
            'labels' => [['labelImage' => 'data:image/svg+xml;base64,'.base64_encode($svg), 'label' => $truck->license_plate]],
            'qrWidth' => 600,
            'qrHeight' => 680,
        ])->download('truck-'.$truck->id.'-qr.pdf');
    }

    /** @return Builder<Truck> */
    private function truckQuery(): Builder
    {
        return Truck::query()->select(self::COLUMNS)->addSelect(['open_trip_id' => Trip::query()->select('id')->whereColumn('truck_id', 'trucks.id')->where('status', '<>', 'closed')->latest('created_at')->limit(1)]);
    }

    /**
     * Store admins and staff may view every library file through UploadPolicy.
     * Only image records can be attached as truck photos.
     *
     * @param  list<int|string>  $attachedPhotoIds
     * @return Builder<Upload>
     */
    private function photoQuery(array $attachedPhotoIds = []): Builder
    {
        return Upload::query()->select(['id', 'file_original_name', 'file_name', 'external_link'])->where('type', 'image')
            ->when(! auth('store')->user()?->can('viewAny', Upload::class), fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('user_id', auth('store')->id())->orWhereIn('id', $attachedPhotoIds)));
    }
}
