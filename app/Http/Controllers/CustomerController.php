<?php
namespace App\Http\Controllers;
use App\Models\Address;
use App\Models\Country;
use App\Models\StoreUser;
use App\Services\TradeLicenseStorage;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
class CustomerController extends Controller
{
    protected function userService() { return app(UserService::class); }
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
        abort_unless(auth()->user()->is_rep, 403);

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
            flash(translate('Your Account has been Activated'))->success();
        } else {
            flash(translate('Sorry, we could not Activate your account. Please try again'))->error();
        }

        return redirect()->route('dashboard');
    }
}
