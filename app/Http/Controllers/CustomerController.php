<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\RegisterController;
use App\Services\TradeLicenseStorage;
use App\Models\Address;
use App\Models\Country;
use App\Models\Customer;
use App\Models\RepresentativePackage;
use App\Models\StoreUser;
use App\Notifications\AppEmailVerificationNotification;
use App\Services\UserService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CustomerController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * See Auth\LoginController::middleware()'s docblock for why this
     * replaces the constructor-based $this->middleware() call.
     *
     * @return array<int, \Illuminate\Routing\Controllers\Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_customers', only: ['index', 'store', 'update', 'store_customer', 'update_password', 'update_customer_notes', 'repStore', 'change_activity_status', 'change_password', 'create', 'downloadTradeLicense', 'edit', 'important', 'not_important', 'pages_views', 'repCreate', 'repIndex', 'show', 'verify', 'whatsapp_contact']),
            new Middleware('permission:login_as_customer', only: ['login']),
            new Middleware('permission:ban_customer', only: ['ban']),
            new Middleware('permission:delete_customer', only: ['destroy', 'bulk_customer_delete', 'archive', 'restore']),
        ];
    }
    protected $userService;

    protected function userService()
    {
        return app(UserService::class);
    }


    public function index(Request $request)
    {
        // dd($request->all());
        $sort_search = null;
        $users = StoreUser::where('user_type', 'customer')->where('is_rep', false)->whereNull('is_delete');

        if ($request->has('search')) {
            $sort_search = $request->search;
            $users->where(function ($q) use ($sort_search) {
                $q->where('name', 'like', '%' . $sort_search . '%')
                    ->orWhere('email', 'like', '%' . $sort_search . '%')
                    ->orWhere('AccSysID', 'like', '%' . $sort_search . '%')
                    ->orWhere('phone', 'like', '%' . $sort_search . '%');
            });
        }

        $isActive = $request->has('active');
        $isFromApi = $request->has('from_api');
        $notImportant = $request->has('not_important');
        $newUsers = $request->has('new_users');
        $isRegistrationCompleted = $request->has('registration_completed');

        $allActive = $request->has('allActive');
        $allFromApi = $request->has('allFromApi');
        $allRegistrationCompleted = $request->has('allRegistrationCompleted');

        if (!$request->has('allCustomers')) {
            if ($allActive || $allFromApi || $allRegistrationCompleted || $newUsers) {
                if ($allFromApi) {
                    $users = $users->where('from_api', true);
                } elseif ($allActive) {
                    $users = $users->where('admin_verified', true);
                } elseif ($allRegistrationCompleted) {
                    $users = $users->where('registration_completed', true);
                } elseif ($newUsers) {
                    $users = $users->where('activity_status', 'new_user');
                }
            } elseif ($isActive || $isFromApi || $isRegistrationCompleted) {
                $users->where('admin_verified', $isActive ?? false);
                $users->where('from_api', $isFromApi ?? false);
                $users->where('registration_completed', $isRegistrationCompleted ?? false);
            }
        }

        if ($notImportant) {
            $users = $users->where('is_important', false);
        } else {
            $users = $users->where('is_important', true);
        }

        $users = $users->paginate(15);

        // dd($users->toSql()); // Add this line
        return view('shop.backend.customer.customers.index', compact('users', 'sort_search'));
    }

    public function archive(Request $request)
    {
        // dd($request->all());
        $sort_search = null;
        $users = StoreUser::where('user_type', 'customer')->where('is_rep', false)->where('is_delete', 1);

        if ($request->has('search')) {
            $sort_search = $request->search;
            $users->where(function ($q) use ($sort_search) {
                $q->where('name', 'like', '%' . $sort_search . '%')
                    ->orWhere('email', 'like', '%' . $sort_search . '%')
                    ->orWhere('AccSysID', 'like', '%' . $sort_search . '%')
                    ->orWhere('phone', 'like', '%' . $sort_search . '%');
            });
        }

        $isActive = $request->has('active');
        $isFromApi = $request->has('from_api');
        $notImportant = $request->has('not_important');
        $newUsers = $request->has('new_users');
        $isRegistrationCompleted = $request->has('registration_completed');

        $allActive = $request->has('allActive');
        $allFromApi = $request->has('allFromApi');
        $allRegistrationCompleted = $request->has('allRegistrationCompleted');

        if (!$request->has('allCustomers')) {
            if ($allActive || $allFromApi || $allRegistrationCompleted || $newUsers) {
                if ($allFromApi) {
                    $users = $users->where('from_api', true);
                } elseif ($allActive) {
                    $users = $users->where('admin_verified', true);
                } elseif ($allRegistrationCompleted) {
                    $users = $users->where('registration_completed', true);
                } elseif ($newUsers) {
                    $users = $users->where('activity_status', 'new_user');
                }
            } elseif ($isActive || $isFromApi || $isRegistrationCompleted) {
                $users->where('admin_verified', $isActive ?? false);
                $users->where('from_api', $isFromApi ?? false);
                $users->where('registration_completed', $isRegistrationCompleted ?? false);
            }
        }

        if ($notImportant) {
            $users = $users->where('is_important', false);
        } else {
            $users = $users->where('is_important', true);
        }

        $users = $users->paginate(15);

        // dd($users->toSql()); // Add this line
        return view('shop.backend.customer.customers.archive', compact('users', 'sort_search'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $countries = Country::where('status', 1)->get();

        return view('shop.backend.customer.customers.create', compact('countries'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required',
            'email'         => 'required|unique:users|email',
            'phone'         => 'required|unique:users',
        ]);

        $response['status'] = 'Error';

        $user = StoreUser::create($request->only([
            'name',
            'email',
            'phone',
            'company_name',
            'country',
            'city',
            'address',
            'postal_code',
            'shipping_address',
            'company_address',
            'tax_number',
        ]));
        $user->forceFill(['user_type' => 'customer'])->save();

        $customer = new Customer;

        $customer->user_id = $user->id;
        $customer->save();

        if (isset($user->id)) {
            $html = '';
            $html .= '<option value="">
                        ' . translate("Walk In Customer") . '
                    </option>';
            foreach (Customer::all() as $key => $customer) {
                if ($customer->user) {
                    $html .= '<option value="' . $customer->user->id . '" data-contact="' . $customer->user->email . '">
                                ' . $customer->user->name . '
                            </option>';
                }
            }

            $response['status'] = 'Success';
            $response['html'] = $html;
        }

        echo json_encode($response);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $customer = StoreUser::find($id);
        $countries = Country::where('status', 1)->get();

        return view('shop.backend.customer.customers.edit', compact('customer', 'countries'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes',
            'country' => 'sometimes|max:255',
            'tax_number' => 'sometimes|max:100',
            'company_address' => 'sometimes',
            'company_name' => 'sometimes',
            'shipping_address' => 'sometimes',
            'bank_account_number' => 'sometimes|max:100',
            'member_serial' => 'sometimes',
            'AccSysID' => 'sometimes',
            'trade_license' => 'file|mimes:doc,docx,pdf,png,jpeg,bmb|max:20000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $customer = StoreUser::find($id);

        // Update customer data
        $customer->name = $request->name;
        $customer->email = $request->email;
        $customer->phone = $request->phone;
        $customer->country = $request->country;
        $customer->tax_number = $request->tax_number;
        $customer->company_address = $request->company_address;
        $customer->company_name = $request->company_name;
        $customer->shipping_address = $request->shipping_address;
        $customer->bank_account_number = $request->bank_account_number;
        $customer->member_serial = $request->member_serial ?? $customer->member_serial;
        $customer->is_rep = ($request->is_rep == 'on') ? true : false;
        $customer->AccSysID = $request->AccSysID ?? $customer->AccSysID;

        // Handle trade_license file upload
        // if ($request->hasFile('trade_license')) {
        //     $tradeLicensePath = $request->file('trade_license')->store('trade_licenses', 'public');
        //     $customer->trade_license = $tradeLicensePath;
        // }

        if (request()->hasFile('trade_license')) {
            $customer->trade_license = app(TradeLicenseStorage::class)->store(request()->file('trade_license'));
        }

        $customer->save();

        if ($customer->is_rep) {
            flash(translate('Representative has been updated successfully.'))->success();
            return redirect()->route('representatives.index');
        } else {
            flash(translate('Customer has been updated successfully.'))->success();
            return redirect()->route('customers.edit', $customer->id);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $customer = StoreUser::findOrFail($id);
        // $customer->customer_products()->delete();
        $customer->is_delete = 1;
        $customer->save();

        if ($customer->is_rep) {
            flash(translate('Representative has been deleted successfully'))->success();
        } else {
            flash(translate('Customer has been deleted successfully'))->success();
        }
        return redirect()->back();
    }

    public function restore($id)
    {
        $customer = StoreUser::findOrFail($id);
        // $customer->customer_products()->delete();
        $customer->is_delete = null;
        $customer->save();

        if ($customer->is_rep) {
            flash(translate('Representative has been restored successfully'))->success();
        } else {
            flash(translate('Customer has been restored successfully'))->success();
        }
        return redirect()->back();
    }

    public function bulk_customer_delete(Request $request)
    {
        if ($request->id) {
            foreach ($request->id as $customer_id) {
                $customer = StoreUser::findOrFail($customer_id);
                $customer->customer_products()->delete();
                $this->destroy($customer_id);
            }
        }

        return 1;
    }

    public function login($id)
    {
        $user = StoreUser::findOrFail(decrypt($id));

        auth()->login($user, true);

        return redirect()->route('dashboard');
    }

    public function ban($id)
    {
        $user = StoreUser::findOrFail(decrypt($id));

        if ($user->banned == 1) {
            $user->banned = 0;
            flash(translate('Customer UnBanned Successfully'))->success();
        } else {
            $user->banned = 1;
            flash(translate('Customer Banned Successfully'))->success();
        }

        $user->save();

        return back();
    }


    public function verify($id)
    {
        $user = StoreUser::findOrFail(decrypt($id));

        if ($user->admin_verified == 1) {
            $user->admin_verified = 0;
            $user->admin_verified_at = null;
            $user->last_order_at = null; // for consistency reason
            flash(translate('Customer UnVerified Successfully'))->success();
        } else {
            $user->admin_verified = 1;
            $user->admin_verified_at = Carbon::today();
            flash(translate('Customer Verified Successfully'))->success();
        }

        $user->save();

        return back();
    }

    public function downloadTradeLicense(Request $req, TradeLicenseStorage $licenses)
    {
        return $licenses->download($req['filename']) ?? abort(404);
    }

    public function repIndex(Request $request)
    {
        $sort_search = null;
        $representatives = StoreUser::where('user_type', 'customer')->where('is_rep', true)->whereNull('is_delete');

        if ($request->has('search')) {
            $sort_search = $request->search;
            $representatives->where(function ($q) use ($sort_search) {
                $q->where('name', 'like', '%' . $sort_search . '%')
                    ->orWhere('email', 'like', '%' . $sort_search . '%')
                    ->orWhere('rep_serial', 'like', '%' . $sort_search . '%')
                    ->orWhere('rep_id', 'like', '%' . $sort_search . '%');
            });
        }

        $representatives = $representatives->with('representativePackage')
            ->orderByRaw('CAST(rep_id AS SIGNED) asc')
            ->paginate(15);

        return view('shop.backend.representatives.index', compact('representatives', 'sort_search'));
    }

    public function repCreate()
    {
        return view('shop.backend.representatives.create');
    }
    public function repStore(Request $request)
    {
        $request->validate([
            'name'          => 'required',
            'email'         => 'required|unique:users|email',
            'phone'         => 'required|unique:users',
        ]);

        $response['status'] = 'Error';

        $user = new StoreUser;
        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->is_rep = true;
        $user->password = bcrypt($request->password);
        $user->admin_verified = true;
        $user->user_type = 'customer';
        $user->email_verified_at = now();
        $user->save();

        // Create a Default address
        $address = new Address;
        $country_id = Country::where('name', 'Netherlands')->value('id');
        $address->user_id       = $user->id;
        $address->address       = 'Default Shipping address';
        $address->country_id    = $country_id;
        $address->phone         = $user->phone;
        $address->set_default   = 1;
        $address->save();

        flash(translate('Representative has been added successfully'))->success();

        return redirect()->route('representatives.index');
    }

    public function create_customer()
    {
        if (!auth()->user()->is_rep) {
            flash(translate('This action is for Representatives only'))->error();
            return back();
        }

        $countries = Country::where('status', 1)->orderBy('priority')->get();

        return view('shop.frontend.user.create_user', compact('countries'));
    }

    public function store_customer(Request $request)
    {

        $data =  Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
            'email' => 'required|email|unique:users,email',
            'tax_number' => 'required|max:100',
            'bank_account_number' => 'required|max:100',
            'trade_license' => 'required|file|mimes:doc,docx,pdf,png,jpeg,bmb|max:20000',
            'country' => 'required',
            'phone' => 'required',
            'company_address' => 'required|string',
            'shipping_address' => 'required|string'
        ]);

        if ($data->fails()) {
            return redirect()->back()->withErrors($data)->withInput();
        }

        $user = StoreUser::create([
            'name' => $request->name,
            'company_name' => $request->company_name,
            'email' => $request->email,
            'password' => $request->password,
            'company_address' => $request->company_address,
            'shipping_address' => $request->shipping_address,
            'tax_number' => $request->tax_number,
            'bank_account_number' => $request->bank_account_number,
            'phone' => $request->phone,
            'country' => $request->country,
            'registration_completed' => 0,
        ]);
        $user->forceFill(['user_type' => 'customer', 'email_verified_at' => now()])->save();
        if (request()->hasFile('trade_license')) {
            $user->trade_license = app(TradeLicenseStorage::class)->store(request()->file('trade_license'));
        }

        // Create an address for the user Task:BS-130
        $country_id = Country::where('name', $request->country)->value('id');
        $address = new Address;
        $address->user_id       = $user->id;
        $address->address       = $request->shipping_address;
        $address->country_id    = $country_id;
        $address->phone         = $request->phone;
        $address->set_default   = 1;
        $address->save();

        $user->save();

        $this->userService()->createCustomerOnOtajer($user);

        flash(translate('Customer added successfully.'))->success();
        return redirect()->route('customers.edit', $user->id);
    }


    // send a terms agreement email to the user, so he can accept them and activate his account.
    public function terms_agreements($id)
    {
        $user = StoreUser::find($id);
        if (! $user->registration_completed) {
            if ($user->bank_account_number && $user->trade_license && $user->company_address && $user->shipping_address && $user->tax_number && $user->phone) {
                try {
                    $user->sendTermsAgreementNotification();
                    flash(translate('Email Sent Successfully'))->success();
                } catch (Exception $e) {
                    flash(translate('Error, Please Try Again'))->error();
                }
            } else {
                flash(translate('User information incomplete yet !  please complete it'))->error();
            }
        } else {
            flash(translate('User is already verified'))->info();
        }

        return redirect()->back();
    }

    // after the user Agrees on the terms and conditions from his email
    public function account_activation($code)
    {
        $user = StoreUser::where('verification_code', $code)->first();
        if ($user != null) {
            $user->admin_verified = 1; // can see the prices
            $user->admin_verified_at = Carbon::today();
            $user->registration_completed = true;
            $user->save();
            auth()->login($user, true);
            flash(translate('Your Account has been Activated'))->success();
        } else {
            flash(translate('Sorry, we could not Activate your account. Please try again'))->error();
        }

        return redirect()->route('dashboard');
    }



    public function change_password($id)
    { // for rep or customer
        $customer = StoreUser::find($id);

        if ($customer) {
            return view('shop.backend.customer.customers.edit_password', compact('customer'));
        } else {
            return back();
        }
    }


    public function update_password(Request $request)
    { // for rep or customer
        $customer = StoreUser::find($request->id);

        if ($customer) {
            $password = $request->password;
            $password_confirmation = $request->password_confirmation;
            if ($password == $password_confirmation) {
                $customer->password = bcrypt($password);
                $customer->save();
                flash('Password updated successfully')->success();
                if ($customer->is_rep) {
                    return redirect()->route('representatives.index');
                } else {

                    return redirect()->route('customers.index');
                }
            } else {
                flash('ERROR: Passwords do not match')->error();
                return redirect()->back();
            }
        } else {
            flash('Error, User not found, Try again')->error();
            return redirect()->back();
        }
    }

    public function whatsapp_contact($customer_id)
    {
        $customer = StoreUser::findOrFail($customer_id);

        if ($customer) {
            $formattedPhoneNumber = $customer->phone;

            if (!$formattedPhoneNumber) {
                flash('The customer did not add a phone number')->error();
                return redirect()->back();
            }

            $phoneUtil = PhoneNumberUtil::getInstance();
            $numberProto = $phoneUtil->parse($formattedPhoneNumber, 'NL');
            $formattedPhoneNumber = $phoneUtil->format($numberProto, PhoneNumberFormat::INTERNATIONAL);

            $url = isset($formattedPhoneNumber) ? 'https://wa.me/' . str_replace(' ', '', $formattedPhoneNumber) : null;

            return redirect($url);
        } else {
            flash('Customer not found')->error();
            return redirect()->back();
        }
    }

    public function important($customer_id)
    {
        $customer = StoreUser::findOrFail($customer_id);

        if ($customer) {
            $customer->is_important = 1;
            $customer->save();
            flash('Customer marked as important successfully')->success();
            return redirect()->route('customers.index');
        } else {
            flash('Customer not found')->error();
            return redirect()->route('customers.index');
        }
    }

    public function not_important($customer_id)
    {
        $customer = StoreUser::findOrFail($customer_id);

        if ($customer) {
            $customer->is_important = 0;
            $customer->save();
            flash('Customer marked as not important successfully')->success();
            return redirect()->route('customers.index');
        } else {
            flash('Customer not found')->error();
            return redirect()->route('customers.index');
        }
    }

    public function change_activity_status(Request $request)
    {
        $customer = StoreUser::findOrFail($request->user_id);

        if ($customer) {
            $customer->activity_status = $request->new_status;
            $customer->save();
            flash('Customer status changed successfully')->success();
            return redirect()->route('customers.index');
        } else {
            flash('Customer not found')->error();
            return redirect()->route('customers.index');
        }
    }

    public function update_customer_notes(Request $request)
    {
        $user = StoreUser::find($request->id);
        if (!$user) {
            return 0;
        }

        $user->admin_notes = $request->admin_notes;
        if ($user->save()) {
            return 1;
        }
        return 0;
    }

    public function pages_views(Request $request, $user_id)
    {
        $user_exist = StoreUser::where('id', $user_id)->exists();
        if (!$user_exist) {
            return abort(404);
        }

        // Retrieve page views grouped by viewable item with counts
        $query = StoreUser::find($user_id)
            ->pageViews()
            ->with('viewable')
            ->select('viewable_id', \DB::raw('count(*) as view_count'), 'viewable_type', \DB::raw('MAX(viewed_at) as last_viewed_at'))
            ->groupBy('viewable_id', 'viewable_type')
            ->orderBy('view_count', 'desc');


        // Apply filter for products or categories but not both
        if ($request->has('products') && !$request->has('categories')) {
            $query->where('viewable_type', 'App\Models\Product');
        } elseif ($request->has('categories') && !$request->has('products')) {
            $query->where('viewable_type', 'App\Models\Category');
        }

        $userViews = $query->paginate(15);

        return view('shop.backend.customer.customers.views', compact('userViews'));
    }
}
