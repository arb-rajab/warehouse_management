<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditTranslatedResourceRequest;
use App\Http\Requests\Store\Admin\FilterCitiesRequest;
use App\Http\Requests\Store\Admin\StoreCityRequest;
use App\Http\Requests\Store\Admin\UpdateCityRequest;
use App\Http\Requests\Store\Admin\UpdateReferenceStatusRequest;
use App\Http\Resources\Store\CityResource;
use App\Http\Resources\Store\StateResource;
use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Language;
use App\Models\State;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class CityController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('permission:manage_shipping_cities')];
    }

    public function index(FilterCitiesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');
        $stateId = $request->validated('state_id');

        return Inertia::render('Store/Admin/Shipping/Cities/Index', [
            'cities' => $this->paginated(CityResource::collection(
                City::query()
                    ->select(['id', 'name', 'state_id', 'cost', 'status'])
                    ->with([
                        'state:id,name,country_id,status',
                        'city_translations:id,city_id,name,lang',
                    ])
                    ->when($search, fn ($query, string $value) => $query->where('name', 'like', "%{$value}%"))
                    ->when($stateId, fn ($query, int $value) => $query->where('state_id', $value))
                    ->orderByDesc('status')
                    ->orderBy('name')
                    ->paginate($perPage)
                    ->withQueryString()
            )),
            'states' => StateResource::collection($this->activeStates()),
            'filters' => ['search' => $search, 'state_id' => $stateId, 'per_page' => $perPage],
        ]);
    }

    public function store(StoreCityRequest $request): RedirectResponse
    {
        $city = new City;
        $city->name = $request->validated('name');
        $city->state_id = $request->integer('state_id');
        $city->cost = $request->validated('cost');
        $city->save();

        return redirect()->route('cities.index');
    }

    public function edit(EditTranslatedResourceRequest $request, City $city): Response
    {
        $lang = $request->validated('lang') ?? config('app.default_language');
        $city->load(['state:id,name,country_id,status', 'city_translations:id,city_id,name,lang']);

        return Inertia::render('Store/Admin/Shipping/Cities/Edit', [
            'city' => new CityResource($city),
            'lang' => $lang,
            'name' => data_get(
                $city->city_translations
                    ->filter(fn (CityTranslation $translation): bool => $translation->lang === $lang)
                    ->first(),
                'name',
                $city->name,
            ),
            'languages' => Language::query()->select(['code', 'name'])->orderBy('id')->get(),
            'states' => StateResource::collection($this->activeStates()),
        ]);
    }

    public function update(UpdateCityRequest $request, City $city): RedirectResponse
    {
        $lang = $request->validated('lang');

        if ($lang === config('app.default_language')) {
            $city->name = $request->validated('name');
        }

        $city->state_id = $request->integer('state_id');
        $city->cost = $request->validated('cost');
        $city->save();

        CityTranslation::query()->updateOrCreate(
            ['city_id' => $city->id, 'lang' => $lang],
            ['name' => $request->validated('name')],
        );

        return redirect()->route('cities.index');
    }

    public function destroy(Request $request, City $city): RedirectResponse
    {
        $city->city_translations()->delete();
        $city->delete();

        return $this->redirectPreservingQuery('cities.index', $request);
    }

    public function updateStatus(UpdateReferenceStatusRequest $request, City $city): RedirectResponse
    {
        $city->status = $request->boolean('status') ? 1 : 0;
        $city->save();

        return $this->redirectPreservingQuery('cities.index', $request);
    }

    /**
     * @return Collection<int, State>
     */
    private function activeStates(): Collection
    {
        return State::query()
            ->select(['id', 'name', 'country_id', 'status'])
            ->where('status', 1)
            ->orderBy('name')
            ->get();
    }
}
