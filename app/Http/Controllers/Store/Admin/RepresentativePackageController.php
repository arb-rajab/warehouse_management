<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\ChecksCustomerAccount;
use App\Http\Requests\Store\Admin\AssignRepresentativePackageRequest;
use App\Http\Requests\Store\Admin\FilterRepresentativePackagesRequest;
use App\Http\Requests\Store\Admin\StoreRepresentativePackageRequest;
use App\Http\Requests\Store\Admin\UpdateRepresentativePackageRequest;
use App\Http\Resources\Store\RepresentativePackageResource;
use App\Models\RepresentativePackage;
use App\Models\StoreUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RepresentativePackageController extends Controller implements HasMiddleware
{
    use ChecksCustomerAccount;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_representative_packages', only: ['index']),
            new Middleware('permission:add_representative_package', only: ['create', 'store']),
            new Middleware('permission:edit_representative_package', only: ['edit', 'update']),
            new Middleware('permission:delete_representative_package', only: ['destroy']),
            new Middleware('permission:assign_representative_package', only: ['assign']),
        ];
    }

    public function index(FilterRepresentativePackagesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Representatives/Packages/Index', [
            'packages' => $this->paginated(RepresentativePackageResource::collection(
                RepresentativePackage::query()->select(['id', 'name', 'percentage'])->orderBy('id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Representatives/Packages/Create');
    }

    public function store(StoreRepresentativePackageRequest $request): RedirectResponse
    {
        RepresentativePackage::create($request->validated());

        return redirect()->route('rep.packages.index');
    }

    public function edit(RepresentativePackage $package): Response
    {
        return Inertia::render('Store/Admin/Representatives/Packages/Edit', ['package' => new RepresentativePackageResource($package)]);
    }

    public function update(UpdateRepresentativePackageRequest $request, RepresentativePackage $package): RedirectResponse
    {
        DB::transaction(function () use ($request, $package) {
            $package = RepresentativePackage::query()->lockForUpdate()->findOrFail($package->id);
            $package->update($request->validated());
            StoreUser::query()->where('user_type', 'customer')->where('is_rep', true)->where('rep_package_id', $package->id)
                ->update(['rep_discount_percentage' => $package->percentage]);
        });

        return redirect()->route('rep.packages.index');
    }

    public function destroy(Request $request, RepresentativePackage $package): RedirectResponse
    {
        DB::transaction(function () use ($package) {
            $package = RepresentativePackage::query()->lockForUpdate()->findOrFail($package->id);
            StoreUser::query()->where('rep_package_id', $package->id)->update(['rep_package_id' => null, 'rep_discount_percentage' => 0]);
            $package->delete();
        });

        return $this->redirectPreservingQuery('rep.packages.index', $request);
    }

    public function assign(AssignRepresentativePackageRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $package = RepresentativePackage::query()->lockForUpdate()->findOrFail($request->integer('package_id'));
            $representative = StoreUser::query()->where('user_type', 'customer')->where('is_rep', true)->whereNull('is_delete')
                ->lockForUpdate()->findOrFail($request->integer('user_id'));
            $this->assertCustomer($representative);
            $representative->forceFill(['rep_package_id' => $package->id, 'rep_discount_percentage' => $package->percentage])->save();
        });

        return redirect()->route('representatives.index');
    }
}
