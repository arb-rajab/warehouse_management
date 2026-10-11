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
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [new Middleware('permission:shipping_country_setting')];
    }

    public function index(FilterCountriesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');

        return Inertia::render('Store/Admin/Shipping/Countries/Index', [
            'countries' => $this->paginated(CountryResource::collection(
                Country::query()
                    ->select(['id', 'code', 'name', 'zone_id', 'status'])
                    ->when($search, fn ($query, string $value) => $query->where('name', 'like', "%{$value}%"))
                    ->orderByDesc('status')
                    ->orderBy('name')
                    ->paginate($perPage)
                    ->withQueryString()
            )),
            'filters' => ['search' => $search, 'per_page' => $perPage],
        ]);
    }

    public function updateStatus(UpdateReferenceStatusRequest $request, Country $country): RedirectResponse
    {
        $country->status = $request->boolean('status') ? 1 : 0;
        $country->save();

        return $this->redirectPreservingQuery('countries.index', $request);
    }
}
