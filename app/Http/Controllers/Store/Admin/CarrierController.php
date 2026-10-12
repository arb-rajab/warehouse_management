<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\ProvidesStoreUploadPreviews;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterCarriersRequest;
use App\Http\Requests\Store\Admin\StoreCarrierRequest;
use App\Http\Requests\Store\Admin\UpdateCarrierRequest;
use App\Http\Requests\Store\Admin\UpdateCarrierStatusRequest;
use App\Http\Resources\Store\CarrierResource;
use App\Models\Carrier;
use App\Models\CarrierRange;
use App\Models\CarrierRangePrice;
use App\Models\Upload;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CarrierController extends Controller implements HasMiddleware
{
    use ProvidesStoreUploadPreviews;

    private const array COLUMNS = ['id', 'name', 'transit_time', 'logo', 'free_shipping', 'status'];

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:manage_carriers')];
    }

    public function index(FilterCarriersRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $query = Carrier::query()->select(self::COLUMNS);
        $query->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->validated('search').'%'));

        return Inertia::render('Store/Admin/Setup/Carriers/Index', [
            'carriers' => $this->paginated(CarrierResource::collection($query->orderByDesc('id')->paginate($perPage)->withQueryString())),
            'filters' => ['per_page' => $perPage, 'search' => $request->validated('search') ?? ''],
        ]);
    }

    /** @return Collection<int, Zone> */
    private function zones(): Collection
    {
        return Zone::query()->select(['id', 'name'])->orderBy('id')->get();
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Setup/Carriers/Create', ['zones' => $this->zones()]);
    }

    public function edit(Request $request, Carrier $carrier): Response
    {
        $carrier = Carrier::query()->select(self::COLUMNS)->with([
            'carrier_ranges' => fn ($query) => $query->select(['id', 'carrier_id', 'billing_type', 'delimiter1', 'delimiter2'])->orderBy('id'),
            'carrier_ranges.carrier_range_prices:id,carrier_range_id,zone_id,price',
        ])->findOrFail($carrier->id);
        $logo = Upload::query()->select(['id', 'user_id', 'file_original_name', 'type', 'file_size', 'file_name', 'external_link'])->find($carrier->logo);

        return Inertia::render('Store/Admin/Setup/Carriers/Edit', [
            'carrier' => new CarrierResource($carrier),
            'zones' => $this->zones(),
            'initialFiles' => $this->uploadPreviews($request, $logo),
        ]);
    }

    public function store(StoreCarrierRequest $request): RedirectResponse
    {
        DB::transaction(fn () => $this->saveCarrier(new Carrier, $request->validated()));

        return redirect()->route('carriers.index');
    }

    public function update(UpdateCarrierRequest $request, Carrier $carrier): RedirectResponse
    {
        DB::transaction(function () use ($request, $carrier): void {
            $carrier = Carrier::query()->lockForUpdate()->findOrFail($carrier->id);
            $carrier->carrier_range_prices()->delete();
            $carrier->carrier_ranges()->delete();
            $this->saveCarrier($carrier, $request->validated());
        });

        return redirect()->route('carriers.index');
    }

    /** @param array<string, mixed> $data */
    private function saveCarrier(Carrier $carrier, array $data): void
    {
        $carrier->name = $data['carrier_name'];
        $carrier->transit_time = $data['transit_time'];
        $carrier->logo = $data['logo'] ?? null;
        $carrier->free_shipping = (bool) $data['shipping_type'];
        $carrier->save();
        if ($carrier->free_shipping) {
            return;
        }
        foreach ($data['delimiter1'] as $index => $start) {
            $range = new CarrierRange;
            $range->carrier_id = $carrier->id;
            $range->billing_type = $data['billing_type'];
            $range->delimiter1 = $start;
            $range->delimiter2 = $data['delimiter2'][$index];
            $range->save();
            foreach ($data['zones'] as $zone) {
                $price = new CarrierRangePrice;
                $price->carrier_id = $carrier->id;
                $price->carrier_range_id = $range->id;
                $price->zone_id = $zone;
                $price->price = $data['carrier_price'][$zone][$index];
                $price->save();
            }
        }
    }

    public function destroy(Request $request, Carrier $carrier): RedirectResponse
    {
        DB::transaction(function () use ($carrier): void {
            $carrier = Carrier::query()->lockForUpdate()->findOrFail($carrier->id);
            $carrier->carrier_range_prices()->delete();
            $carrier->carrier_ranges()->delete();
            $carrier->delete();
        });

        return $this->redirectPreservingQuery('carriers.index', $request);
    }

    public function updateStatus(UpdateCarrierStatusRequest $request): RedirectResponse
    {
        $carrier = Carrier::query()->findOrFail($request->integer('id'));
        $carrier->status = $request->boolean('status');
        $carrier->save();

        return $this->redirectPreservingQuery('carriers.index', $request);
    }
}
