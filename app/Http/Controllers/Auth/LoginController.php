<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\StoreUser;
use App\Services\SocialAccounts;
use App\Services\SocialRevoke;
use Auth;
use GeneaLabs\LaravelSocialiter\Facades\Socialiter;
use GuzzleHttp\Client;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Session;
use Socialite;
use Storage;

class LoginController extends Controller implements HasMiddleware
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    /*protected $redirectTo = '/';*/

    /**
     * Show the application's login form.
     *
     * Overrides AuthenticatesUsers::showLoginForm(), which renders
     * `auth.login`: the store's views live under `shop.` in this app, and the
     * sweep that prefixed every view() call never reached laravel/ui's own
     * traits in vendor/.
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view('shop.auth.login');
    }


    /**
     * Redirect the user to the Google authentication page.
     *
     * @return Response
     */
    public function redirectToProvider($provider)
    {
        if (request()->get('query') == 'mobile_app') {
            request()->session()->put('login_from', 'mobile_app');
        }
        if ($provider == 'apple') {
            return Socialite::driver("sign-in-with-apple")
                ->scopes(["name", "email"])
                ->redirect();
        }
        return Socialite::driver($provider)->redirect();
    }

    public function handleAppleCallback(Request $request)
    {
        return $this->signInWithProvider($request, 'sign-in-with-apple', 'apple');
    }

    /**
     * Obtain the user information from the provider.
     *
     * The provider's `state` is checked: the callback used to read the profile
     * through `stateless()`, which skips that check, so a login callback
     * forged by another site could sign a victim in as the attacker's
     * provider account.
     *
     * @return Response
     */
    public function handleProviderCallback(Request $request, $provider)
    {
        if (session('login_from') == 'mobile_app') {
            return $this->mobileHandleProviderCallback($request, $provider);
        }

        return $this->signInWithProvider($request, $provider, $provider);
    }

    /**
     * Signs in (or registers) the store account the provider's profile names
     * and sends it where its type belongs.
     *
     * @param  string  $driver  The Socialite driver, which for Apple differs from the stored provider name.
     */
    private function signInWithProvider(Request $request, string $driver, string $provider)
    {
        try {
            $social = Socialite::driver($driver)->user();
        } catch (\Exception $e) {
            flash(translate("Something Went wrong. Please try again."))->error();
            return redirect()->route('user.login');
        }

        $user = app(SocialAccounts::class)->resolve($provider, $social);

        if ($user === null) {
            flash(translate('An account with this email already exists. Sign in with your password instead.'))->error();
            return redirect()->route('user.login');
        }

        auth()->login($user, true);
        $request->session()->regenerate();

        if (session('temp_user_id') != null) {
            Cart::where('temp_user_id', session('temp_user_id'))
                ->update([
                    'user_id' => $user->id,
                    'temp_user_id' => null
                ]);

            Session::forget('temp_user_id');
        }

        if (session('link') != null) {
            return redirect(session('link'));
        }

        if ($user->user_type == 'seller') {
            return redirect()->route('seller.dashboard');
        }

        return redirect()->route('dashboard');
    }

    public function mobileHandleProviderCallback($request, $provider)
    {
        $return_provider = '';
        $result = false;
        if ($provider) {
            $return_provider = $provider;
            $result = true;
        }
        return response()->json([
            'result' => $result,
            'provider' => $return_provider
        ]);
    }

    /**
     * Validate the user login request.
     *
     * @param  Request  $request
     * @return void
     *
     * @throws ValidationException
     */
    protected function validateLogin(Request $request)
    {
        $request->validate([
            'email'    => 'required_without:phone',
            'phone'    => 'required_without:email',
            'password' => 'required|string',
        ]);
    }

    /**
     * Get the needed authorization credentials from the request.
     *
     * @param  Request  $request
     * @return array
     */
    protected function credentials(Request $request)
    {
        if ($request->get('phone') != null) {
            return ['phone' => "+{$request['country_code']}{$request['phone']}", 'password' => $request->get('password')];
        } elseif ($request->get('email') != null) {
            return $request->only($this->username(), 'password');
        }
    }

    /**
     * Check user's role and redirect user based on their role
     * @return
     */
    public function authenticated()
    {
        if (session('temp_user_id') != null) {
            Cart::where('temp_user_id', session('temp_user_id'))
                ->update(
                    [
                        'user_id' => auth()->user()->id,
                        'temp_user_id' => null,
                    ]
                );

            Session::forget('temp_user_id');
        }

        if (auth()->user()->user_type == 'admin' || auth()->user()->user_type == 'staff') {
            return redirect()->route('shop.admin.dashboard');
        } elseif (auth()->user()->user_type == 'seller') {
            return redirect()->route('seller.dashboard');
        } else {

            if (session('link') != null) {
                return redirect(session('link'));
            } else {
                return redirect()->route('dashboard');
            }
        }
    }

    /**
     * Get the failed login response instance.
     *
     * @param  Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws ValidationException
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        flash(translate('Invalid login credentials'))->error();
        return back();
    }

    /**
     * Log the user out of the application.
     *
     * @param  Request  $request
     * @return Response
     */
    public function logout(Request $request)
    {
        if (auth()->user() != null && (auth()->user()->user_type == 'admin' || auth()->user()->user_type == 'staff')) {
            $redirect_route = 'shop.login';
        } else {
            $redirect_route = 'home';
        }

        //User's Cart Delete
        if (auth()->user()) {
            Cart::where('user_id', auth()->user()->id)->delete();
        }

        $this->guard()->logout();

        $request->session()->invalidate();

        return $this->loggedOut($request) ?: redirect()->route($redirect_route);
    }

    public function account_deletion(Request $request)
    {

        $redirect_route = 'home';

        if (auth()->user()) {
            Cart::where('user_id', auth()->user()->id)->delete();
        }

        // if (auth()->user()->provider) {
        //     $social_revoke =  new SocialRevoke;
        //     $revoke_output = $social_revoke->apply(auth()->user()->provider);

        //     if ($revoke_output) {
        //     }
        // }

        $auth_user = auth()->user();

        // user images delete from database and file storage
        $uploads = $auth_user->uploads;
        if($uploads){
            foreach ($uploads as $upload) {
                if (config('filesystems.default') == 's3') {
                    Storage::disk('s3')->delete($upload->file_name);
                    if (file_exists(public_path() . '/' . $upload->file_name)) {
                        unlink(public_path() . '/' . $upload->file_name);
            $upload->delete();

                    }
                } else {
                    unlink(public_path() . '/' . $upload->file_name);
            $upload->delete();

                }
            }
        }

        $auth_user->customer_products()->delete();

        StoreUser::destroy(auth()->user()->id);

        auth()->guard()->logout();
        $request->session()->invalidate();

        flash(translate("Your account deletion successfully done."))->success();
        return redirect()->route($redirect_route);
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */

    /**
     * Get the middleware that should be assigned to the controller.
     *
     * Constructor-based `$this->middleware()` (the legacy laravel/ui
     * scaffold's own pattern) was removed from the base Controller in
     * Laravel 11 — every controller still using it throws "Call to
     * undefined method ::middleware()" the moment it's instantiated, before
     * any action method runs. This is the replacement `HasMiddleware`
     * static method Laravel 11+ expects instead.
     *
     * @return array<int, \Illuminate\Routing\Controllers\Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('guest', except: ['logout', 'account_deletion']),
        ];
    }
}
