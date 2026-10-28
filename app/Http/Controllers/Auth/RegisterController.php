<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\BusinessSetting;
use App\Models\Cart;
use App\Models\Country;
use App\Models\Customer;
use App\Models\StoreUser;
use App\Notifications\EmailVerificationNotification;
use App\Rules\Recaptcha;
use App\Services\TradeLicenseStorage;
use App\Services\UserService;
use Carbon\Carbon;
use Cookie;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Nexmo;
use Session;


class RegisterController extends Controller implements HasMiddleware
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Show the application registration form.
     *
     * Overrides RegistersUsers::showRegistrationForm(), which renders
     * `auth.register`; see LoginController::showLoginForm().
     *
     * @return \Illuminate\View\View
     */
    public function showRegistrationForm()
    {
        return view('shop.auth.register');
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */

    /**
     * Get the middleware that should be assigned to the controller.
     *
     * See LoginController::middleware()'s docblock for why this replaces
     * the constructor-based `$this->middleware()` call.
     *
     * @return array<int, \Illuminate\Routing\Controllers\Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('guest'),
        ];
    }

    protected $userService;

    protected function userService()
    {
        return app(UserService::class);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|string|max:191',
            'company_name' => 'required|string|max:191',
            'password' => 'required|string|min:6|confirmed',
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique(StoreUser::class, 'email')],
            'country' => 'required|string|max:30',
            'phone' => 'required|string|max:20',
            'tax_number' => 'nullable|string|max:100',
            'company_address' => 'nullable|string|max:191',
            'trade_license' => 'nullable|file|mimes:doc,docx,pdf,png,jpg,jpeg|max:20000',
            'g-recaptcha-response' => Recaptcha::rules(),
        ], Recaptcha::messages());
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return StoreUser
     */
    protected function create(array $data)
    {


        $tradeLicense = isset($data['trade_license'])
            ? app(TradeLicenseStorage::class)->store($data['trade_license'])
            : null;


        $user = StoreUser::create([
            'name' => $data['name'],
            'company_name' => $data['company_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'],
            'country' => $data['country'],
            'tax_number' => $data['tax_number'] ?? null,
            'company_address' => $data['company_address'] ?? null,
            'trade_license' => $tradeLicense,
            'registration_completed' => false,
        ]);

        // Create an address for the user Task:BS-130
        $country_id = Country::where('name', $data['country'])->value('id');
        $address = new Address;
        $address->user_id       = $user->id;
        $address->country_id    = $country_id;
        $address->phone         = $data['phone'];
        $address->set_default   = 1;
        $address->save();

        if(session('temp_user_id') != null){
            Cart::where('temp_user_id', session('temp_user_id'))
                    ->update([
                        'user_id' => $user->id,
                        'temp_user_id' => null
            ]);

            Session::forget('temp_user_id');
        }

        if(Cookie::has('referral_code')){
            $referral_code = Cookie::get('referral_code');
            $referred_by_user = StoreUser::where('referral_code', $referral_code)->first();
            if($referred_by_user != null){
                $user->referred_by = $referred_by_user->id;
                $user->save();
            }
        }

        return $user;
    }

    public function register(Request $request)
    {
        $data = $this->validator($request->all())->validate();
        $user = $this->create($data);

        $this->guard()->login($user);

        $this->userService()->createCustomerOnOtajer($user); //2. then create an account for him in otajer store.


        if($user->email != null){
            if(BusinessSetting::where('type', 'email_verification')->first()?->value != 1){
                $user->email_verified_at = now();
                $user->save();
                flash(__('storefront.auth.registration_successful'))->success();
            }
            else {
                try {
                    $user->sendEmailVerificationNotification();
                    flash(__('storefront.auth.registration_successful_please_verify_your_email'))->success();
                } catch (\Throwable $th) {
                    $user->delete();
                    flash(__('storefront.auth.registration_failed_please_try_again_later'))->error();
                }
            }
        }




        return $this->registered($request, $user)
            ?: redirect($this->redirectPath());
    }

    protected function registered(Request $request, $user)
    {
        if ($user->email == null) {
            return redirect()->route('verification');
        }elseif(session('link') != null){
            return redirect(session('link'));
        }else {
            return redirect()->route('home');
        }
    }
}
