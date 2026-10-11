<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\ProvidesStoreReferenceOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditReferenceTranslationRequest;
use App\Http\Requests\Store\Admin\FilterCitiesRequest;
use App\Http\Requests\Store\Admin\StoreCityRequest;
use App\Http\Requests\Store\Admin\UpdateCityRequest;
use App\Http\Requests\Store\Admin\UpdateReferenceStatusRequest;
use App\Http\Resources\Store\CityResource;
use App\Models\City;
use App\Models\CityTranslation;
use App\Models\State;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CityController extends Controller implements HasMiddleware
{
    use ProvidesStoreReferenceOptions;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:manage_shipping_cities')];
    }

    public function index(FilterCitiesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $query = City::query()->select(['id', 'name', 'state_id', 'cost', 'status'])->with(['state:id,name', 'city_translations:id,city_id,lang,name']);
        $query->when($request->filled('sort_city'), fn ($query) => $query->where('name', 'like', '%'.$request->validated('sort_city').'%'));
        $query->when($request->filled('sort_state'), fn ($query) => $query->where('state_id', $request->integer('sort_state')));

        return Inertia::render('Store/Admin/Setup/Cities/Index', [
            'cities' => $this->paginated(CityResource::collection($query->orderByDesc('status')->orderBy('id')->paginate($perPage)->withQueryString())),
            'filters' => ['per_page' => $perPage, 'sort_city' => $request->validated('sort_city'), 'sort_state' => $request->validated('sort_state')],
            'states' => $this->stateOptions(),
        ]);
    }

    public function updateStatus(UpdateReferenceStatusRequest $request, City $city): RedirectResponse
    {
        $city->status = $request->boolean('status') ? 1 : 0;
        $city->save();

        return $this->redirectPreservingQuery('cities.index', $request);
    }

    public function edit(EditReferenceTranslationRequest $request, City $city): Response
    {
        $lang = $request->validated('lang') ?? config('app.default_language');
        $city->load(['state:id,name', 'city_translations:id,city_id,lang,name']);
        $translation = $city->city_translations->firstWhere('lang', $lang);

        return Inertia::render('Store/Admin/Setup/Cities/Edit', [
            'city' => new CityResource($city),
            'states' => $this->stateOptions(),
            'lang' => $lang, 'languages' => $this->languageOptions(), 'name' => $translation->name ?? $city->name,
        ]);
    }

    public function store(StoreCityRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $city = new City;
            $city->name = $request->validated('name');
            $city->state_id = $request->validated('state_id');
            $city->cost = $request->validated('cost');
            $city->save();
        });

        return redirect()->route('cities.index');
    }

    public function update(UpdateCityRequest $request, City $city): RedirectResponse
    {
        DB::transaction(function () use ($request, $city) {
            if ($request->validated('lang') === config('app.default_language')) {
                $city->name = $request->validated('name');
            }
            $city->state_id = $request->validated('state_id');
            $city->cost = $request->validated('cost');
            $city->save();
            CityTranslation::query()->updateOrCreate(['city_id' => $city->id, 'lang' => $request->validated('lang')], $request->safe()->only(['name']));
        });

        return redirect()->route('cities.index');
    }

    public function destroy(Request $request, City $city): RedirectResponse
    {
        DB::transaction(function () use ($city) {
            $city->city_translations()->delete();
            $city->delete();
        });

        return $this->redirectPreservingQuery('cities.index', $request);
    }

    /** @return Collection<int, State> */
    private function stateOptions(): Collection
    {
        return State::query()->select(['id', 'name'])->where('status', 1)->orderBy('name')->get();
    }
}
