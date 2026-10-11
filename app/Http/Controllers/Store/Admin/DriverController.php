<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterDriversRequest;
use App\Http\Requests\Store\Admin\StoreDriverRequest;
use App\Http\Requests\Store\Admin\UpdateDriverRequest;
use App\Http\Resources\Store\DriverResource;
use App\Models\Country;
use App\Models\StoreUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DriverController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_drivers', only: ['index']),
            new Middleware('permission:add_driver', only: ['create', 'store']),
            new Middleware('permission:edit_driver', only: ['edit', 'update']),
            new Middleware('permission:delete_driver', only: ['destroy']),
        ];
    }

    public function index(FilterDriversRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');

        return Inertia::render('Store/Admin/Trips/Drivers/Index', [
            'drivers' => $this->paginated(DriverResource::collection(
                StoreUser::query()->where('user_type', 'driver')
                    ->select(['id', 'name', 'email', 'phone', 'country'])
                    ->when(is_string($search) && $search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                        $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');
                    }))
                    ->orderBy('id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['per_page' => $perPage, 'search' => $search ?? ''],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Trips/Drivers/Create', ['countries' => $this->countries()]);
    }

    public function store(StoreDriverRequest $request): RedirectResponse
    {
        $driver = new StoreUser;
        $driver->name = $request->validated('name');
        $driver->email = $request->validated('email');
        $driver->phone = $request->validated('phone');
        $driver->country = $request->validated('country');
        $driver->password = $request->validated('password');
        $driver->user_type = 'driver';
        $driver->email_verified_at = now();
        $driver->forceFill(['registration_completed' => 1])->save();

        return redirect()->route('drivers.index');
    }

    public function edit(StoreUser $driver): Response
    {
        abort_unless($driver->user_type === 'driver', 404);

        return Inertia::render('Store/Admin/Trips/Drivers/Edit', [
            'driver' => new DriverResource($driver),
            'countries' => $this->countries(),
        ]);
    }

    public function update(UpdateDriverRequest $request, StoreUser $driver): RedirectResponse
    {
        abort_unless($driver->user_type === 'driver', 404);
        $driver->name = $request->validated('name');
        $driver->phone = $request->validated('phone');
        $driver->country = $request->validated('country');
        if ($request->filled('password')) {
            $driver->password = $request->validated('password');
        }
        $driver->save();

        return redirect()->route('drivers.index');
    }

    public function destroy(Request $request, StoreUser $driver): RedirectResponse
    {
        abort_unless($driver->user_type === 'driver', 404);

        DB::transaction(function () use ($driver): void {
            $driver->trips()->update(['driver_id' => null]);
            $driver->delete();
        });

        return $this->redirectPreservingQuery('drivers.index', $request);
    }

    /** @return array<int, array{id: int, name: string}> */
    private function countries(): array
    {
        return Country::query()->select(['id', 'name'])->where('status', 1)
            ->orderBy('priority')->orderBy('id')->get()
            ->map(fn (Country $country): array => ['id' => $country->id, 'name' => $country->name])->all();
    }
}
