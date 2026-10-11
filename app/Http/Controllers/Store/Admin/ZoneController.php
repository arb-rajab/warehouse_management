<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterZonesRequest;
use App\Http\Requests\Store\Admin\StoreZoneRequest;
use App\Http\Requests\Store\Admin\UpdateZoneRequest;
use App\Http\Resources\Store\CountryResource;
use App\Http\Resources\Store\ZoneResource;
use App\Models\Country;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ZoneController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('permission:manage_zones')];
    }

    public function index(FilterZonesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Shipping/Zones/Index', [
            'zones' => $this->paginated(ZoneResource::collection(
                Zone::query()
                    ->select(['id', 'name', 'status'])
                    ->orderByDesc('id')
                    ->paginate($perPage)
                    ->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Shipping/Zones/Create', [
            'countries' => CountryResource::collection($this->availableCountries()),
        ]);
    }

    public function store(StoreZoneRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $zone = new Zone;
            $zone->name = $request->validated('name');
            $zone->status = true;
            $zone->save();

            Country::query()->whereIn('id', $request->validated('country_id'))->update(['zone_id' => $zone->id]);
        });

        return redirect()->route('zones.index');
    }

    public function edit(Zone $zone): Response
    {
        return Inertia::render('Store/Admin/Shipping/Zones/Edit', [
            'zone' => new ZoneResource($zone),
            'countries' => CountryResource::collection($this->availableCountries($zone)),
            'countryIds' => Country::query()->where('zone_id', $zone->id)->orderBy('id')->pluck('id'),
        ]);
    }

    public function update(UpdateZoneRequest $request, Zone $zone): RedirectResponse
    {
        DB::transaction(function () use ($request, $zone) {
            $zone->name = $request->validated('name');
            $zone->save();

            Country::query()->where('zone_id', $zone->id)->update(['zone_id' => 0]);
            Country::query()->whereIn('id', $request->validated('country_id'))->update(['zone_id' => $zone->id]);
        });

        return redirect()->route('zones.index');
    }

    public function destroy(Request $request, Zone $zone): RedirectResponse
    {
        DB::transaction(function () use ($zone) {
            Country::query()->where('zone_id', $zone->id)->update(['zone_id' => 0]);
            $zone->delete();
        });

        return $this->redirectPreservingQuery('zones.index', $request);
    }

    /**
     * @return Collection<int, Country>
     */
    private function availableCountries(?Zone $zone = null): Collection
    {
        return Country::query()
            ->select(['id', 'code', 'name', 'zone_id', 'status'])
            ->where('status', 1)
            ->where(fn ($query) => $query->where('zone_id', 0)->when($zone, fn ($inner) => $inner->orWhere('zone_id', $zone->id)))
            ->orderBy('name')
            ->get();
    }
}
