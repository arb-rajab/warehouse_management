<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterCountriesRequest;
use App\Http\Requests\Store\Admin\UpdateReferenceStatusRequest;
use App\Http\Resources\Store\CountryResource;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class CountryController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:shipping_country_setting')];
    }

    public function index(FilterCountriesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $query = Country::query()->select(['id', 'name', 'code', 'status']);
        $query->when($request->filled('sort_country'), fn ($query) => $query->where('name', 'like', '%'.$request->validated('sort_country').'%'));

        return Inertia::render('Store/Admin/Setup/Countries/Index', [
            'countries' => $this->paginated(CountryResource::collection($query->orderByDesc('status')->orderBy('id')->paginate($perPage)->withQueryString())),
            'filters' => ['per_page' => $perPage, 'sort_country' => $request->validated('sort_country')],
        ]);
    }

    public function updateStatus(UpdateReferenceStatusRequest $request, Country $country): RedirectResponse
    {
        $country->status = $request->boolean('status') ? 1 : 0;
        $country->save();

        return $this->redirectPreservingQuery('countries.index', $request);
    }
}
