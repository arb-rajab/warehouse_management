<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\SecondEmailVerifyMailManager;
use App\Models\StoreUser;
use App\Utility\SmsUtility;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Password;
use Mail;

class ForgotPasswordController extends Controller implements HasMiddleware
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Display the form to request a password reset link.
     *
     * Overrides SendsPasswordResetEmails::showLinkRequestForm(), which
     * renders `auth.passwords.email`; see LoginController::showLoginForm().
     *
     * @return \Illuminate\View\View
     */
    public function showLinkRequestForm()
    {
        return view('shop.auth.passwords.email');
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

    /**
     * Send a reset link to the given user.
     *
     * @param  Request  $request
     * @return RedirectResponse|JsonResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        $phone = "+{$request['country_code']}{$request['phone']}";
        if (filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            $user = StoreUser::where('email', $request->email)->first();
            if ($user != null) {
                $user->verification_code = rand(100000,999999);
                $user->save();

                $array['view'] = 'shop.emails.verification';
                $array['from'] = config('mail.from.address');
                $array['subject'] = translate('Password Reset');
                $array['content'] = translate('Verification Code is').': '. $user->verification_code;

                Mail::to($user->email)->queue(new SecondEmailVerifyMailManager($array));

                return view('shop.auth.passwords.reset');
            }
            else {
                flash(translate('No account exists with this email'))->error();
                return back();
            }
        }
        else{
            $user = StoreUser::where('phone', $phone)->first();
            if ($user != null) {
                $user->verification_code = rand(100000,999999);
                $user->save();
                SmsUtility::password_reset($user);
                return view('otp_systems.frontend.auth.passwords.reset_with_phone');
            }
            else {
                flash(translate('No account exists with this phone number'))->error();
                return back();
            }
        }
    }
}
