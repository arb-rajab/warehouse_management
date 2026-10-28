<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreRepresentativeCustomerRequest;
use App\Models\Address;
use App\Models\Country;
use App\Models\StoreUser;
use App\Services\TradeLicenseStorage;
use App\Services\UserService;
use Carbon\Carbon;
class CustomerController extends Controller
{
    protected function userService() { return app(UserService::class); }
    public function create_customer()
    {
        if (!auth()->user()->is_rep) {
            flash(__('storefront.common.this_action_is_for_representatives_only'))->error();
            return back();
        }

        $countries = Country::where('status', 1)->orderBy('priority')->get();

        return view('shop.frontend.user.create_user', compact('countries'));
    }

    public function store_customer(StoreRepresentativeCustomerRequest $request)
    {
        $user = StoreUser::create([
            'name' => $request->validated('name'),
            'company_name' => $request->validated('company_name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'company_address' => $request->validated('company_address'),
            'shipping_address' => $request->validated('shipping_address'),
            'tax_number' => $request->validated('tax_number'),
            'bank_account_number' => $request->validated('bank_account_number'),
            'phone' => $request->validated('phone'),
            'country' => $request->validated('country'),
            'registration_completed' => 0,
        ]);
        $user->forceFill(['user_type' => 'customer', 'email_verified_at' => now()])->save();
        if (request()->hasFile('trade_license')) {
            $user->trade_license = app(TradeLicenseStorage::class)->store(request()->file('trade_license'));
        }

        // Create an address for the user Task:BS-130
        $country_id = Country::where('name', $request->validated('country'))->value('id');
        $address = new Address;
        $address->user_id       = $user->id;
        $address->address       = $request->validated('shipping_address');
        $address->country_id    = $country_id;
        $address->phone         = $request->validated('phone');
        $address->set_default   = 1;
        $address->save();

        $user->save();

        $this->userService()->createCustomerOnOtajer($user);

        flash(__('storefront.common.customer_added_successfully'))->success();
        return redirect()->route('dashboard');
    }


    // send a terms agreement email to the user, so he can accept them and activate his account.

    public function account_activation($code)
    {
        $user = StoreUser::where('verification_code', $code)->first();
        if ($user != null) {
            $user->admin_verified = 1; // can see the prices
            $user->admin_verified_at = Carbon::today();
            $user->registration_completed = true;
            $user->save();
            auth()->login($user, true);
            flash(__('storefront.auth.your_account_has_been_activated'))->success();
        } else {
            flash(__('storefront.auth.sorry_we_could_not_activate_your_account_please_try_again'))->error();
        }

        return redirect()->route('dashboard');
    }
}
