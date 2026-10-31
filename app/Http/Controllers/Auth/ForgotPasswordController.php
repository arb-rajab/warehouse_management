<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\SecondEmailVerifyMailManager;
use App\Models\StoreUser;
use App\Services\StoreVerificationCodes;
use App\Utility\SmsUtility;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
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
     * Send a reset code to the given user.
     *
     * The answer is the same whether or not an account matches, and when its
     * last code is still inside the resend cooldown: it used to say "No
     * account exists with this email" (or phone number), which tells anyone
     * which addresses are customers.
     *
     * The signature is the trait's: laravel/ui's SendsPasswordResetEmails
     * declares `sendResetLinkEmail(Request $request)`, so the service is
     * resolved here rather than injected.
     *
     * @param  Request  $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        $codes = app(StoreVerificationCodes::class);

        if (filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            $user = StoreUser::where('email', $request->email)->first();
            $code = $user !== null ? $codes->issueUnlessCoolingDown($user) : null;

            if ($code !== null) {
                $array['view'] = 'shop.emails.verification';
                $array['from'] = config('mail.from.address');
                $array['subject_key'] = 'storefront.auth.password_reset_subject';
                $array['content_key'] = 'storefront.auth.verification_code_message';
                $array['content_parameters'] = ['code' => $code];

                Mail::to($user->email)->queue(new SecondEmailVerifyMailManager($array, $user));
            }

            return view('shop.auth.passwords.reset');
        }

        $user = StoreUser::where('phone', "+{$request['country_code']}{$request['phone']}")->first();
        $code = $user !== null ? $codes->issueUnlessCoolingDown($user) : null;

        if ($code !== null) {
            SmsUtility::password_reset($user, $code);
        }

        // Back to the form that asked, with the same notice either way. The
        // phone-code reset page the SMS add-on supplies is not part of this
        // app, and the notice must not depend on an account having matched.
        flash(__('storefront.auth.if_an_account_matches_a_code_has_been_sent'))->info();

        return back();
    }
}
