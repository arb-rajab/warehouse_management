<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterZonesRequest;
use App\Http\Requests\Store\Admin\StoreZoneRequest;
use App\Http\Requests\Store\Admin\UpdateZoneRequest;
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
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:manage_zones')];
    }

    public function index(FilterZonesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $query = Zone::query()->select(['id', 'name', 'status']);

        return Inertia::render('Store/Admin/Setup/Zones/Index', [
            'zones' => $this->paginated(ZoneResource::collection($query->orderByDesc('id')->paginate($perPage)->withQueryString())),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    /** @return Collection<int, Country> */
    private function countries(?Zone $zone = null): Collection
    {
        $zoneId = $zone === null ? 0 : $zone->id;

        return Country::query()->select(['id', 'name'])->where('status', 1)->where(fn ($query) => $query->where('zone_id', 0)->orWhere('zone_id', $zoneId))->orderBy('name')->get();
    }

    public function edit(Zone $zone): Response
    {
        return Inertia::render('Store/Admin/Setup/Zones/Edit', [
            'zone' => new ZoneResource($zone),
            'countries' => $this->countries($zone), 'countryIds' => Country::query()->where('zone_id', $zone->id)->pluck('id'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Setup/Zones/Create', [
            'countries' => $this->countries(),
        ]);
    }

    public function store(StoreZoneRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $zone = Zone::create(['name' => $request->validated('name'), 'status' => 1]);
            Country::query()->whereIn('id', $request->validated('country_id'))->update(['zone_id' => $zone->id]);
        });

        return redirect()->route('zones.index');
    }

    public function update(UpdateZoneRequest $request, Zone $zone): RedirectResponse
    {
        DB::transaction(function () use ($request, $zone) {
            $zone->update(['name' => $request->validated('name')]);
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
}
