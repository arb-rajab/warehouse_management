<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterStatesRequest;
use App\Http\Requests\Store\Admin\StoreStateRequest;
use App\Http\Requests\Store\Admin\UpdateReferenceStatusRequest;
use App\Http\Requests\Store\Admin\UpdateStateRequest;
use App\Http\Resources\Store\StateResource;
use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StateController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:manage_shipping_states')];
    }

    public function index(FilterStatesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $query = State::query()->select(['id', 'name', 'country_id', 'status'])->with(['country:id,name']);
        $query->when($request->filled('sort_state'), fn ($query) => $query->where('name', 'like', '%'.$request->validated('sort_state').'%'));
        $query->when($request->filled('sort_country'), fn ($query) => $query->where('country_id', $request->integer('sort_country')));

        return Inertia::render('Store/Admin/Setup/States/Index', [
            'states' => $this->paginated(StateResource::collection($query->orderByDesc('status')->orderBy('id')->paginate($perPage)->withQueryString())),
            'filters' => ['per_page' => $perPage, 'sort_state' => $request->validated('sort_state'), 'sort_country' => $request->validated('sort_country')],
            'countries' => $this->countryOptions(),
        ]);
    }

    public function updateStatus(UpdateReferenceStatusRequest $request, State $state): RedirectResponse
    {
        $state->status = $request->boolean('status') ? 1 : 0;
        $state->save();
        if ($state->status) {
            $state->cities()->update(['status' => 1]);
        }

        return $this->redirectPreservingQuery('states.index', $request);
    }

    public function edit(State $state): Response
    {
        $state->load(['country:id,name']);

        return Inertia::render('Store/Admin/Setup/States/Edit', [
            'state' => new StateResource($state),
            'countries' => $this->countryOptions(),
        ]);
    }

    public function store(StoreStateRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $state = new State;
            $state->name = $request->validated('name');
            $state->country_id = $request->validated('country_id');
            $state->save();
        });

        return redirect()->route('states.index');
    }

    public function update(UpdateStateRequest $request, State $state): RedirectResponse
    {
        DB::transaction(function () use ($request, $state) {
            $state->name = $request->validated('name');
            $state->country_id = $request->validated('country_id');
            $state->save();
        });

        return redirect()->route('states.index');
    }

    public function destroy(Request $request, State $state): RedirectResponse
    {
        DB::transaction(function () use ($state) {
            $state->delete();
        });

        return $this->redirectPreservingQuery('states.index', $request);
    }

    /** @return Collection<int, Country> */
    private function countryOptions(): Collection
    {
        return Country::query()->select(['id', 'name'])->where('status', 1)->orderBy('name')->get();
    }
}
