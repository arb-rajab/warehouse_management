<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OTPVerificationController;
use App\Models\StoreUser;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\VerifiesEmails;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class VerificationController extends Controller implements HasMiddleware
{
    /*
    |--------------------------------------------------------------------------
    | Email Verification Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling email verification for any
    | user that recently registered with the application. Emails may also
    | be re-sent if the user didn't receive the original email message.
    |
    */

    use VerifiesEmails;

    /**
     * Where to redirect users after verification.
     *
     * @var string
     */
    protected $redirectTo = '/';

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
     * The legacy constructor had `auth` commented out whole, so that
     * verification_confirmation()'s emailed link works for a guest.
     * show(), verify() and resend() all read $request->user() unguarded, so
     * a guest got a 500 from them instead of the login page.
     *
     * @return array<int, \Illuminate\Routing\Controllers\Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('auth', except: ['verification_confirmation']),
            new Middleware('signed', only: ['verify', 'verification_confirmation']),
            new Middleware('throttle:6,1', only: ['verify', 'resend']),
        ];
    }

    /**
     * Show the email verification notice.
     *
     * @param  Request  $request
     * @return Response
     */
    public function show(Request $request)
    {
        if ($request->user()->email != null) {
            return $request->user()->hasVerifiedEmail()
                            ? redirect($this->redirectPath())
                            : view('shop.auth.verify');
        }
        else {
            $otpController = new OTPVerificationController;
            $otpController->send_code($request->user());
            return redirect()->route('verification');
        }
    }


    /**
     * Resend the email verification notification.
     *
     * @param  Request  $request
     * @return Response
     */
    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect($this->redirectPath());
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('resent', true);
    }

    /**
     * Confirms the email address an EmailVerificationNotification link was
     * sent to, and signs the user in.
     *
     * The link is signed and expires (`signed` middleware); `hash` ties it to
     * the address it was sent to, so a link for an address the user has since
     * changed away from confirms nothing; and it only counts while the address
     * is unverified, so opening it again signs nobody in. This used to look a
     * user up by `verification_code` alone, from a guest, with no limit on
     * tries and no expiry, so any six-digit reset code was a login.
     */
    public function verification_confirmation(Request $request, int $id)
    {
        $user = StoreUser::find($id);

        $isCurrentLink = $user?->email !== null
            && hash_equals(sha1($user->email), (string) $request->query('hash'));

        if ($isCurrentLink && $user->email_verified_at === null) {
            $user->email_verified_at = Carbon::now();
            $user->save();
            auth()->login($user);
            $request->session()->regenerate();
            flash(translate('Your email has been verified successfully'))->success();

            if ($user->user_type == 'seller') {
                return redirect()->route('seller.dashboard');
            }
        } else {
            flash(translate('Sorry, we could not verifiy you. Please try again'))->error();
        }

        return redirect()->route('dashboard');
    }
}
