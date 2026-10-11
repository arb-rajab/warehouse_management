<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterStatesRequest;
use App\Http\Requests\Store\Admin\StoreStateRequest;
use App\Http\Requests\Store\Admin\UpdateReferenceStatusRequest;
use App\Http\Requests\Store\Admin\UpdateStateRequest;
use App\Http\Resources\Store\CountryResource;
use App\Http\Resources\Store\StateResource;
use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class StateController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('permission:manage_shipping_states')];
    }

    public function index(FilterStatesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');
        $countryId = $request->validated('country_id');

        return Inertia::render('Store/Admin/Shipping/States/Index', [
            'states' => $this->paginated(StateResource::collection(
                State::query()
                    ->select(['id', 'name', 'country_id', 'status'])
                    ->with('country:id,code,name,zone_id,status')
                    ->when($search, fn ($query, string $value) => $query->where('name', 'like', "%{$value}%"))
                    ->when($countryId, fn ($query, int $value) => $query->where('country_id', $value))
                    ->orderByDesc('status')
                    ->orderBy('name')
                    ->paginate($perPage)
                    ->withQueryString()
            )),
            'countries' => CountryResource::collection($this->activeCountries()),
            'filters' => ['search' => $search, 'country_id' => $countryId, 'per_page' => $perPage],
        ]);
    }

    public function store(StoreStateRequest $request): RedirectResponse
    {
        $state = new State;
        $state->name = $request->validated('name');
        $state->country_id = $request->integer('country_id');
        $state->save();

        return redirect()->route('states.index');
    }

    public function edit(State $state): Response
    {
        $state->load('country:id,code,name,zone_id,status');

        return Inertia::render('Store/Admin/Shipping/States/Edit', [
            'state' => new StateResource($state),
            'countries' => CountryResource::collection($this->activeCountries()),
        ]);
    }

    public function update(UpdateStateRequest $request, State $state): RedirectResponse
    {
        $state->name = $request->validated('name');
        $state->country_id = $request->integer('country_id');
        $state->save();

        return redirect()->route('states.index');
    }

    public function destroy(Request $request, State $state): RedirectResponse
    {
        $state->delete();

        return $this->redirectPreservingQuery('states.index', $request);
    }

    public function updateStatus(UpdateReferenceStatusRequest $request, State $state): RedirectResponse
    {
        $active = $request->boolean('status');
        $state->status = $active ? 1 : 0;
        $state->save();

        if ($active) {
            $state->cities()->update(['status' => 1]);
        }

        return $this->redirectPreservingQuery('states.index', $request);
    }

    /**
     * @return Collection<int, Country>
     */
    private function activeCountries(): Collection
    {
        return Country::query()
            ->select(['id', 'code', 'name', 'zone_id', 'status'])
            ->where('status', 1)
            ->orderBy('name')
            ->get();
    }
}
