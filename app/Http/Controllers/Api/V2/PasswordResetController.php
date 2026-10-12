<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\OTPVerificationController;
use App\Models\StoreUser;
use App\Notifications\AppEmailVerificationNotification;
use App\Services\StoreVerificationCodes;
use Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PasswordResetController extends Controller
{
    public function __construct(private readonly StoreVerificationCodes $codes)
    {
    }

    /**
     * Sends the account a reset code, if there is one.
     *
     * The answer never says whether an account matched, nor whether a code
     * was held back because the last one is still inside the resend cooldown
     * (StoreVerificationCodes): it used to be a 404 "User is not found".
     */
    public function forgetRequest(Request $request)
    {
        return $this->sendCode($request->send_code_by, $request->email_or_phone, translate('A code is sent'));
    }

    /**
     * Resets the password of the account `email_or_phone` names, if
     * `verification_code` is the live code it was sent.
     *
     * The account used to be looked up by the code alone, so a guessed code
     * reset the password of whichever user held it at that moment, a
     * registration code included. It has to match the named account, is
     * checked as a hash, expires, is used up after a few wrong tries or one
     * right one, and the route is throttled per account (`store-auth`). The
     * new password is validated before the account is looked up, so a 422 says
     * nothing about whether the account exists.
     */
    public function confirmReset(Request $request)
    {
        $identifier = trim((string) $request->email_or_phone);
        $code = (string) $request->verification_code;

        if ($identifier === '' || $code === '') {
            return response()->json([
                'result' => false,
                'message' => translate('Validation Error'),
                'errors' => ['email_or_phone' => [translate('The email or phone and the code are required.')]],
                'user_id' => 0
            ], 422);
        }

        $validatedData = Validator::make($request->all(), [
            'password' => 'required|string|min:6|max:255',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'result' => false,
                'message' => translate('Validation Error'),
                'errors' => $validatedData->errors(),
                'user_id' => 0
            ], 422);
        }

        $user = StoreUser::where(str_contains($identifier, '@') ? 'email' : 'phone', $identifier)->first();

        if ($user === null || ! $this->codes->consume($user, $code)) {
            return response()->json([
                'result' => false,
                'message' => translate('The code is invalid or has expired'),
            ], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'result' => true,
            'message' => translate('Your password is reset.Please login'),
        ], 200);
    }

    public function resendCode(Request $request)
    {
        return $this->sendCode($request->verify_by, $request->email_or_phone, translate('A code is sent again'));
    }

    private function sendCode(?string $by, mixed $identifier, string $message): JsonResponse
    {
        $identifier = trim((string) $identifier);
        $user = null;

        if ($identifier !== '') {
            $user = StoreUser::where($by == 'email' ? 'email' : 'phone', $identifier)->first();
        }

        $code = $user !== null ? $this->codes->issueUnlessCoolingDown($user) : null;

        if ($code !== null) {
            if ($by == 'email') {
                $user->notify(new AppEmailVerificationNotification($code));
            } else {
                (new OTPVerificationController())->send_code($user, $code);
            }
        }

        return response()->json([
            'result' => true,
            'message' => $message,
        ], 200);
    }
}
