<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\OTPVerificationController;
use App\Models\PasswordReset;
use App\Models\StoreUser;
use App\Notifications\AppEmailVerificationNotification;
use App\Notifications\PasswordResetRequest;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function forgetRequest(Request $request)
    {
        if ($request->send_code_by == 'email') {
            $user = StoreUser::where('email', $request->email_or_phone)->first();
        } else {
            $user = StoreUser::where('phone', $request->email_or_phone)->first();
        }


        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => translate('User is not found')], 404);
        }

        if ($user) {
            $user->verification_code = random_int(100000, 999999);
            $user->save();
            if ($request->send_code_by == 'phone') {

                $otpController = new OTPVerificationController();
                $otpController->send_code($user);
            } else {
                $user->notify(new AppEmailVerificationNotification());
            }
        }

        return response()->json([
            'result' => true,
            'message' => translate('A code is sent')
        ], 200);
    }

    /**
     * Resets the password of the account `email_or_phone` names, if
     * `verification_code` is the code it was sent.
     *
     * The account used to be looked up by the code alone, so a guessed code
     * reset the password of whichever user held it at that moment, a
     * registration code included. It now has to match the named account, and
     * the route is throttled per account (`store-auth`).
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

        $user = StoreUser::where(str_contains($identifier, '@') ? 'email' : 'phone', $identifier)
            ->where('verification_code', $code)
            ->first();

        if ($user != null) {
            $validatedData = Validator::make($request->all(), [
                    'password' => 'required|string|min:6',
                ]);
            if ($validatedData->fails()) {
                return response()->json([
                    'result' => false,
                    'message' => translate('Validation Error'),
                    'errors' => $validatedData->errors(),
                    'user_id' => 0
                ], 422);
            }
            $user->verification_code = null;
            $user->password = Hash::make($request->password);
            $user->save();
            return response()->json([
                'result' => true,
                'message' => translate('Your password is reset.Please login'),
            ], 200);
        } else {
            return response()->json([
                'result' => false,
                'message' => translate('No user is found'),
            ], 404);
        }
    }

    public function resendCode(Request $request)
    {

        if ($request->verify_by == 'email') {
            $user = StoreUser::where('email', $request->email_or_phone)->first();
        } else {
            $user = StoreUser::where('phone', $request->email_or_phone)->first();
        }


        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => translate('User is not found')], 404);
        }

        $user->verification_code = random_int(100000, 999999);
        $user->save();

        if ($request->verify_by == 'email') {
            $user->notify(new AppEmailVerificationNotification());
        } else {
            $otpController = new OTPVerificationController();
            $otpController->send_code($user);
        }



        return response()->json([
            'result' => true,
            'message' => translate('A code is sent again'),
        ], 200);
    }
}
