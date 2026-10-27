<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\ChecksCustomerAccount;
use App\Http\Requests\Store\Admin\BulkCustomersRequest;
use App\Http\Requests\Store\Admin\FilterCustomersRequest;
use App\Http\Requests\Store\Admin\StoreCustomerRequest;
use App\Http\Requests\Store\Admin\UpdateCustomerActivityRequest;
use App\Http\Requests\Store\Admin\UpdateCustomerNotesRequest;
use App\Http\Requests\Store\Admin\UpdateCustomerPasswordRequest;
use App\Http\Requests\Store\Admin\UpdateCustomerRequest;
use App\Http\Resources\Store\CustomerPageViewResource;
use App\Http\Resources\Store\CustomerResource;
use App\Models\Address;
use App\Models\Category;
use App\Models\Country;
use App\Models\Product;
use App\Models\RepresentativePackage;
use App\Models\StoreUser;
use App\Services\TradeLicenseStorage;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerController extends Controller implements HasMiddleware
{
    use ChecksCustomerAccount;

    private const array LIST_COLUMNS = ['id', 'name', 'email', 'phone', 'company_name', 'is_rep', 'is_delete', 'banned', 'admin_verified', 'is_important', 'activity_status', 'rep_id', 'rep_serial', 'rep_package_id'];

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_customers', only: ['index', 'repIndex', 'pageViews', 'downloadTradeLicense', 'whatsappContact']),
            new Middleware('permission:edit_customer', only: ['create', 'store', 'edit', 'update', 'changePassword', 'updatePassword', 'verify', 'important', 'notImportant', 'changeActivityStatus', 'updateNotes', 'sendAgreement', 'repCreate', 'repStore']),
            new Middleware('permission:delete_customer', only: ['archive', 'destroy', 'restore', 'bulkDestroy']),
            new Middleware('permission:ban_customer', only: ['ban']),
            new Middleware('permission:login_as_customer', only: ['login']),
        ];
    }

    public function index(FilterCustomersRequest $request): Response
    {
        return $this->listing($request, false);
    }

    public function repIndex(FilterCustomersRequest $request): Response
    {
        return $this->listing($request, true);
    }

    public function archive(FilterCustomersRequest $request): Response
    {
        $request->merge(['archived' => true]);

        return $this->listing($request, false);
    }

    private function listing(FilterCustomersRequest $request, bool $representatives): Response
    {
        if ($request->boolean('archived')) {
            abort_unless($request->user('store')->can('delete_customer'), 403);
        }
        if (! $representatives && ! $request->has('important')) {
            $request->merge(['important' => ! $request->has('not_important')]);
        }
        $perPage = $this->resolvePerPage($request, 20);
        $query = StoreUser::query()->select(self::LIST_COLUMNS)->where('user_type', 'customer')->where('is_rep', $representatives);
        $request->boolean('archived') ? $query->where('is_delete', 1) : $query->whereNull('is_delete');
        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('email', 'like', $search)->orWhere('phone', 'like', $search)->orWhere('rep_serial', 'like', $search)->orWhere('AccSysID', 'like', $search));
        }
        foreach (['important' => 'is_important', 'verified' => 'admin_verified', 'from_api' => 'from_api', 'registration_completed' => 'registration_completed'] as $filter => $column) {
            if ($request->has($filter)) {
                $query->where($column, $request->boolean($filter));
            }
        }
        if ($request->filled('activity_status')) {
            $query->where('activity_status', $request->string('activity_status'));
        }
        if ($representatives) {
            $query->with('representativePackage:id,name,percentage');
        }

        return Inertia::render('Store/Admin/Customers/Customers/Index', [
            'customers' => $this->paginated(CustomerResource::collection($query->orderBy('id')->paginate($perPage)->withQueryString())),
            'filters' => [...$request->only(['search', 'activity_status']), ...collect(['archived', 'important', 'verified', 'from_api', 'registration_completed'])->filter(fn ($filter) => $request->has($filter))->mapWithKeys(fn ($filter) => [$filter => $request->boolean($filter)])->all(), 'per_page' => $perPage],
            'representatives' => $representatives,
            'packages' => $representatives && $request->user('store')->can('assign_representative_package') ? RepresentativePackage::query()->select(['id', 'name', 'percentage'])->orderBy('id')->get()->map(fn ($package) => ['id' => $package->id, 'name' => $package->name, 'percentage' => (string) $package->percentage])->all() : [],
        ]);
    }

    public function create(): Response
    {
        return $this->createPage(false);
    }

    public function repCreate(): Response
    {
        return $this->createPage(true);
    }

    private function createPage(bool $representative): Response
    {
        return Inertia::render('Store/Admin/Customers/Customers/Create', ['representative' => $representative, 'countries' => $this->countries()]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        return $this->createAccount($request, false);
    }

    public function repStore(StoreCustomerRequest $request): RedirectResponse
    {
        return $this->createAccount($request, true);
    }

    private function createAccount(StoreCustomerRequest $request, bool $representative): RedirectResponse
    {
        $customer = DB::transaction(function () use ($request, $representative) {
            $customer = new StoreUser;
            $customer->fill($request->safe()->except(['trade_license', 'password_confirmation']));
            $customer->user_type = 'customer';
            $customer->is_rep = $representative;
            $customer->admin_verified = $representative;
            $customer->email_verified_at = now();
            if ($request->hasFile('trade_license')) {
                $customer->trade_license = app(TradeLicenseStorage::class)->store($request->file('trade_license'));
            }
            $customer->save();
            $countryId = Country::query()->where('name', $request->input('country', $representative ? 'Netherlands' : null))->value('id');
            if ($request->filled('shipping_address') || ($representative && $countryId !== null)) {
                $address = new Address;
                $address->user_id = $customer->id;
                $address->address = $request->input('shipping_address', 'Default Shipping address');
                $address->country_id = $countryId;
                $address->phone = $customer->phone;
                $address->set_default = 1;
                $address->save();
            }

            return $customer;
        });
        if (! $representative) {
            app(UserService::class)->createCustomerOnOtajer($customer);
        }

        return redirect()->route($representative ? 'representatives.index' : 'customers.index');
    }

    public function edit(StoreUser $customer): Response
    {
        $this->assertCustomer($customer);

        return Inertia::render('Store/Admin/Customers/Customers/Edit', [
            'customer' => [...(new CustomerResource($customer))->resolve(), 'country' => $customer->country, 'tax_number' => $customer->tax_number, 'bank_account_number' => $customer->bank_account_number, 'company_address' => $customer->company_address, 'shipping_address' => $customer->shipping_address, 'member_serial' => $customer->member_serial, 'AccSysID' => $customer->AccSysID, 'trade_license' => $customer->trade_license, 'admin_notes' => $customer->admin_notes, 'registration_completed' => (bool) $customer->registration_completed],
            'countries' => $this->countries(),
            'addresses' => $customer->addresses()->select(['id', 'user_id', 'address', 'country_id', 'state_id', 'city_id', 'postal_code', 'phone', 'latitude', 'longitude', 'set_default'])->orderBy('id')->get()->map(fn ($address) => $address->only(['id', 'address', 'country_id', 'state_id', 'city_id', 'postal_code', 'phone', 'latitude', 'longitude', 'set_default']))->all(),
        ]);
    }

    public function update(UpdateCustomerRequest $request, StoreUser $customer): RedirectResponse
    {
        $this->assertCustomer($customer);
        $customer->fill($request->safe()->except(['trade_license', 'password_confirmation', 'password', 'is_rep']));
        if (array_key_exists('company_name', $request->validated()) && $request->validated('company_name') === null) {
            $customer->company_name = '';
        }
        if (array_key_exists('is_rep', $request->validated())) {
            $customer->forceFill(['is_rep' => $request->boolean('is_rep')]);
        }
        if ($request->filled('password')) {
            $customer->password = $request->validated('password');
        }
        if ($request->hasFile('trade_license')) {
            $customer->trade_license = app(TradeLicenseStorage::class)->store($request->file('trade_license'));
        }
        $customer->save();

        return $this->toList($customer);
    }

    public function destroy(Request $request, StoreUser $customer): RedirectResponse
    {
        $this->assertCustomer($customer);
        $customer->is_delete = 1;
        $customer->save();

        return $this->redirectPreservingQuery($customer->is_rep ? 'representatives.index' : 'customers.index', $request);
    }

    public function restore(StoreUser $customer): RedirectResponse
    {
        $this->assertCustomer($customer);
        $customer->is_delete = null;
        $customer->save();

        return $this->toList($customer);
    }

    public function bulkDestroy(BulkCustomersRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $customers = StoreUser::query()->whereIn('id', $request->validated('id'))->lockForUpdate()->get();
            foreach ($customers as $customer) {
                $this->assertCustomer($customer);
            } foreach ($customers as $customer) {
                $customer->is_delete = 1;
                $customer->save();
            }
        });

        return redirect()->route('customers.index');
    }

    public function login(StoreUser $customer): RedirectResponse
    {
        $this->assertCustomer($customer);
        abort_if($customer->is_delete || $customer->banned, 403);
        auth('store')->login($customer, true);
        request()->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function ban(StoreUser $customer): RedirectResponse
    {
        $this->assertCustomer($customer);
        $customer->banned = $customer->banned ? 0 : 1;
        $customer->save();

        return $this->toList($customer);
    }

    public function verify(StoreUser $customer): RedirectResponse
    {
        $this->assertCustomer($customer);
        $customer->admin_verified = ! $customer->admin_verified;
        $customer->admin_verified_at = $customer->admin_verified ? today()->toDateString() : null;
        if (! $customer->admin_verified) {
            $customer->last_order_at = null;
        } $customer->save();

        return $this->toList($customer);
    }

    public function important(StoreUser $customer): RedirectResponse
    {
        return $this->setImportance($customer, true);
    }

    public function notImportant(StoreUser $customer): RedirectResponse
    {
        return $this->setImportance($customer, false);
    }

    private function setImportance(StoreUser $customer, bool $important): RedirectResponse
    {
        $this->assertCustomer($customer);
        $customer->is_important = $important ? 1 : 0;
        $customer->save();

        return $this->toList($customer);
    }

    public function changeActivityStatus(UpdateCustomerActivityRequest $request, StoreUser $customer): RedirectResponse
    {
        $this->assertCustomer($customer);
        $customer->activity_status = $request->validated('new_status');
        $customer->save();

        return $this->toList($customer);
    }

    public function updateNotes(UpdateCustomerNotesRequest $request, StoreUser $customer): RedirectResponse
    {
        $this->assertCustomer($customer);
        $customer->admin_notes = $request->validated('admin_notes');
        $customer->save();

        return $this->toList($customer);
    }

    public function changePassword(StoreUser $customer): RedirectResponse
    {
        $this->assertCustomer($customer);

        return redirect()->route('customers.edit', $customer);
    }

    public function updatePassword(UpdateCustomerPasswordRequest $request, StoreUser $customer): RedirectResponse
    {
        $this->assertCustomer($customer);
        $customer->password = $request->validated('password');
        $customer->save();

        return $this->toList($customer);
    }

    public function sendAgreement(StoreUser $customer): RedirectResponse
    {
        $this->assertCustomer($customer);
        if ($customer->registration_completed || ! $customer->bank_account_number || ! $customer->trade_license || ! $customer->company_address || ! $customer->shipping_address || ! $customer->tax_number || ! $customer->phone) {
            throw ValidationException::withMessages(['agreement' => __('messages.customer_agreement_incomplete')]);
        }
        $customer->sendTermsAgreementNotification();

        return $this->toList($customer);
    }

    public function downloadTradeLicense(Request $request, TradeLicenseStorage $licenses): BinaryFileResponse
    {
        return $licenses->download($request->route('filename')) ?? abort(404);
    }

    public function whatsappContact(StoreUser $customer): RedirectResponse
    {
        $this->assertCustomer($customer);
        if (! $customer->phone) {
            throw ValidationException::withMessages(['phone' => __('messages.customer_phone_required')]);
        }

        $phones = PhoneNumberUtil::getInstance();
        try {
            $number = $phones->parse($customer->phone, 'NL');
        } catch (NumberParseException) {
            throw ValidationException::withMessages(['phone' => __('messages.customer_phone_invalid')]);
        }

        return redirect()->away('https://wa.me/'.ltrim($phones->format($number, PhoneNumberFormat::E164), '+'));
    }

    public function pageViews(FilterCustomersRequest $request, StoreUser $customer): Response
    {
        $this->assertCustomer($customer);
        $perPage = $this->resolvePerPage($request, 20);
        $query = $customer->pageViews()->select(['viewable_id', 'viewable_type'])->selectRaw('COUNT(*) as view_count, MAX(viewed_at) as last_viewed_at')->whereIn('viewable_type', [Product::class, Category::class])->groupBy('viewable_id', 'viewable_type')->orderByDesc('view_count')->orderBy('viewable_id');
        if ($request->filled('kind')) {
            $query->where('viewable_type', $request->string('kind')->toString() === 'products' ? Product::class : Category::class);
        }
        $views = $query->paginate($perPage)->withQueryString();
        $products = Product::query()->select(['id', 'name', 'slug'])->whereIn('id', $views->getCollection()->where('viewable_type', Product::class)->pluck('viewable_id'))->get()->keyBy('id');
        $categoryNames = Category::query()->select(['id', 'name'])->whereIn('id', $views->getCollection()->where('viewable_type', Category::class)->pluck('viewable_id'))->pluck('name', 'id');
        foreach ($views->getCollection() as $view) {
            $product = $products->get($view->viewable_id);
            $view->setAttribute('view_name', $view->viewable_type === Product::class ? $product?->name : $categoryNames->get($view->viewable_id));
            $view->setAttribute('product_slug', $view->viewable_type === Product::class ? $product?->slug : null);
            $view->setRelation('viewedProduct', $view->viewable_type === Product::class ? $product : null);
        }

        return Inertia::render('Store/Admin/Customers/Customers/Views', ['customer' => ['id' => $customer->id, 'name' => $customer->name], 'views' => $this->paginated(CustomerPageViewResource::collection($views)), 'filters' => ['kind' => $request->input('kind'), 'per_page' => $perPage]]);
    }

    /** @return array<int, array{id:int,name:string}> */
    private function countries(): array
    {
        return Country::query()->select(['id', 'name'])->where('status', 1)->orderBy('priority')->orderBy('id')->get()->map(fn ($country) => ['id' => $country->id, 'name' => $country->name])->all();
    }

    private function toList(StoreUser $customer): RedirectResponse
    {
        return redirect()->route($customer->is_rep ? 'representatives.index' : 'customers.index');
    }
}
