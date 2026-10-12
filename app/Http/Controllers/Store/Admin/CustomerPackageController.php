<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Concerns\ProvidesStoreReferenceOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditReferenceTranslationRequest;
use App\Http\Requests\Store\Admin\FilterCustomerPackagesRequest;
use App\Http\Requests\Store\Admin\StoreCustomerPackageRequest;
use App\Http\Requests\Store\Admin\UpdateCustomerPackageRequest;
use App\Http\Resources\Store\CustomerPackageResource;
use App\Http\Resources\Store\UploadResource;
use App\Models\CustomerPackage;
use App\Models\CustomerPackageTranslation;
use App\Models\Upload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerPackageController extends Controller implements HasMiddleware
{
    use ProvidesStoreReferenceOptions;

    private const array COLUMNS = ['id', 'name', 'amount', 'product_upload', 'logo'];

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_classified_packages', only: ['index']),
            new Middleware('permission:edit_classified_package', only: ['create', 'store', 'edit', 'update']),
            new Middleware('permission:delete_classified_package', only: ['destroy']),
        ];
    }

    public function index(FilterCustomerPackagesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Customers/Packages/Index', [
            'packages' => $this->paginated(CustomerPackageResource::collection(
                CustomerPackage::query()->select(self::COLUMNS)
                    ->with('customer_package_translations:id,customer_package_id,lang,name')
                    ->orderBy('id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
            'defaultLanguage' => config('app.default_language'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Customers/Packages/Create');
    }

    public function edit(EditReferenceTranslationRequest $request, CustomerPackage $customerPackage): Response
    {
        $lang = $request->validated('lang') ?? config('app.default_language');
        $package = CustomerPackage::query()->select(self::COLUMNS)
            ->with('customer_package_translations:id,customer_package_id,lang,name')->findOrFail($customerPackage->id);
        $logo = Upload::query()->select(['id', 'user_id', 'file_original_name', 'type', 'file_size', 'file_name', 'external_link'])->find($package->logo);

        return Inertia::render('Store/Admin/Customers/Packages/Edit', [
            'package' => new CustomerPackageResource($package),
            'name' => $package->getTranslation('name', $lang),
            'lang' => $lang,
            'languages' => $this->languageOptions(),
            'initialFiles' => $logo && ($request->user('store')->can('view_uploads') || $logo->user_id === $request->user('store')->id) && $request->user('store')->can('view', $logo) ? [new UploadResource($logo)] : [],
        ]);
    }

    public function store(StoreCustomerPackageRequest $request): RedirectResponse
    {
        DB::transaction(fn () => $this->savePackage(new CustomerPackage, $request->validated(), config('app.default_language')));

        return redirect()->route('customer_packages.index');
    }

    public function update(UpdateCustomerPackageRequest $request, CustomerPackage $customerPackage): RedirectResponse
    {
        DB::transaction(function () use ($request, $customerPackage): void {
            $package = CustomerPackage::query()->lockForUpdate()->findOrFail($customerPackage->id);
            $this->savePackage($package, $request->validated(), $request->validated('lang'));
        });

        return redirect()->route('customer_packages.edit', ['customerPackage' => $customerPackage->id, 'lang' => $request->validated('lang')]);
    }

    /** @param array<string, mixed> $data */
    private function savePackage(CustomerPackage $package, array $data, string $lang): void
    {
        if (! $package->exists || $lang === config('app.default_language')) {
            $package->name = $data['name'];
        }
        $package->amount = $data['amount'];
        $package->product_upload = $data['product_upload'];
        $package->logo = $data['logo'] ?? null;
        $package->save();
        CustomerPackageTranslation::query()->updateOrCreate(
            ['customer_package_id' => $package->id, 'lang' => $lang], ['name' => $data['name']]
        );
    }

    public function destroy(Request $request, CustomerPackage $customerPackage): RedirectResponse
    {
        DB::transaction(function () use ($customerPackage): void {
            $package = CustomerPackage::query()->lockForUpdate()->findOrFail($customerPackage->id);
            $package->customer_package_translations()->delete();
            $package->delete();
        });

        return $this->redirectPreservingQuery('customer_packages.index', $request);
    }
}
