<?php

/** @noinspection PhpUndefinedClassInspection */

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\OTPVerificationController;
use App\Models\Address;
use App\Models\BusinessSetting;
use App\Models\Cart;
use App\Models\Country;
use App\Models\Customer;
use App\Models\StoreUser;
use App\Notifications\AppEmailVerificationNotification;
use App\Rules\Recaptcha;
use App\Services\SocialAccounts;
use App\Services\SocialRevoke;
use App\Services\StoreVerificationCodes;
use App\Services\UserService;
use App\Utility\PayhereUtility;
use Carbon\Carbon;
use GeneaLabs\LaravelSocialiter\Facades\Socialiter;
use GuzzleHttp\Client as GuzzleClient;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\PersonalAccessToken;
use Socialite;

class AuthController extends Controller
{
    protected $userService;

    public function __construct(private readonly StoreVerificationCodes $codes)
    {
    }

    protected function userService()
    {
        return app(UserService::class);
    }

    public function signup(Request $request)
    {
        $messages = [
            'name.required' => translate('Name is required'),
            'email_or_phone.required' => $request->register_by == 'email' ? translate('Email is required') : translate('Phone is required'),
            'email_or_phone.email' => translate('Email must be a valid email address'),
            'email_or_phone.numeric' => translate('Phone must be a number.'),
            'email_or_phone.unique' => $request->register_by == 'email' ? translate('The email has already been taken') : translate('The phone has already been taken'),
            'password.required' => translate('Password is required'),
            'password.confirmed' => translate('Password confirmation does not match'),
            'password.min' => translate('Minimum 6 digits required for password'),
        ];
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'password' => 'required|string|min:6|max:255|confirmed',
            'email_or_phone' => 'required|email|unique:users,email',
            'country' => 'required|string|max:255',
            'phone' => 'required|string',

        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => "Inputs Validation Error",
                'errors' => $validator->errors()
            ]);
        }

        $user = new StoreUser();
        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->company_name = $request->company_name;
        $user->device_key = $request->device_key;
        $user->registration_completed = false;
        // $user->company_address = $request->company_address;
        // $user->shipping_address = $request->shipping_address;
        // $user->tax_number = $request->tax_number;
        // $user->bank_account_number =  $request->bank_account_number;
        $user->country = $request->country;
        if ($request->register_by == 'email') {
            $user->email = $request->email_or_phone;
        }
        if ($request->register_by == 'phone') {
            $user->phone = $request->email_or_phone;
        }

        // if (request()->hasFile('trade_license')) {
        //     $uploadedFile = request()->file('trade_license');
        //     $pdfFileName = $uploadedFile->getClientOriginalName(); // Get the original filename

        //     // Store the PDF file in the 'local' disk with the specified filename
        //     $pdfPath = $uploadedFile->storeAs('uploads/licenses', $pdfFileName, 'local');

        //     $user->trade_license = $pdfFileName; // Store the path in your database
        // }

        $user->password = bcrypt($request->password);

        $user->email_verified_at = null;
        if ($user->email != null) {
            if (BusinessSetting::where('type', 'email_verification')->first()?->value != 1) {
                $user->email_verified_at = now();
            }
        }

        $user->save();

        if ($user->email_verified_at == null) {
            $code = $this->codes->issue($user);

            if ($request->register_by == 'email') {
                try {
                    $user->notify(new AppEmailVerificationNotification($code));
                } catch (\Exception $e) {
                }
            } else {
                $otpController = new OTPVerificationController();
                $otpController->send_code($user, $code);
            }
        }

        //create token
        $user->createToken('tokens')->plainTextToken;

        // Automatically create an address for the user
        // Create an address for the user Task:BS-130
        $country_id = Country::where('name', $request->country)->value('id');
        $address = new Address;
        $address->user_id       = $user->id;
        //$address->address       = $request->shipping_address;
        $address->country_id    = $country_id;
        $address->phone         = $request->phone;
        $address->set_default   = 1;
        $address->save();

        $this->userService()->createCustomerOnOtajer($user); // create account for him in otajer

        return $this->loginSuccess($user);
    }

    public function resendCode()
    {
        $user = auth()->user();
        $code = $this->codes->issueUnlessCoolingDown($user);

        if ($code === null) {
            return response()->json([
                'result' => false,
                'message' => translate('Please wait a moment before asking for another code'),
            ], 429);
        }

        if ($user->email) {
            try {
                $user->notify(new AppEmailVerificationNotification($code));
            } catch (\Exception $e) {
            }
        } else {
            $otpController = new OTPVerificationController();
            $otpController->send_code($user, $code);
        }

        return response()->json([
            'result' => true,
            'message' => translate('Verification code is sent again'),
        ], 200);
    }

    public function confirmCode(Request $request)
    {
        $user = auth()->user();

        if ($this->codes->consume($user, (string) $request->verification_code)) {
            $user->email_verified_at = date('Y-m-d H:i:s');
            $user->save();
            return response()->json([
                'result' => true,
                'message' => translate('Your account is now verified'),
            ], 200);
        } else {
            return response()->json([
                'result' => false,
                'message' => translate('Code does not match, you can request for resending the code'),
            ], 200);
        }
    }

    public function login(Request $request)
    {
        $messages = [
            'email.required' => $request->login_by == 'email' ? translate('Email is required') : translate('Phone is required'),
            'email.email' => translate('Email must be a valid email address'),
            'email.numeric' => translate('Phone must be a number.'),
            'password.required' => translate('Password is required'),
        ];
        $validator = Validator::make($request->all(), [
            'password' => 'required',
            'login_by' => 'required',
            'email' => [
                'required',
                Rule::when($request->login_by === 'email', ['email', 'required']),
                Rule::when($request->login_by === 'phone', ['numeric', 'required']),
            ]
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => "Email and Password Are Required!"
            ]);
        }

        $delivery_boy_condition = $request->has('user_type') && $request->user_type == 'delivery_boy';
        $seller_condition = $request->has('user_type') && $request->user_type == 'seller';
        $driver_condition = $request->has('user_type') && $request->user_type == 'driver';
        $req_email = $request->email;

        if ($delivery_boy_condition) {
            $user = StoreUser::whereIn('user_type', ['delivery_boy'])
                ->where(function ($query) use ($req_email) {
                    $query->where('email', $req_email)
                        ->orWhere('phone', $req_email);
                })
                ->first();
        } elseif ($seller_condition) {
            $user = StoreUser::whereIn('user_type', ['seller'])
                ->where(function ($query) use ($req_email) {
                    $query->where('email', $req_email)
                        ->orWhere('phone', $req_email);
                })
                ->first();
        } elseif ($driver_condition) {
            $user = StoreUser::whereIn('user_type', ['driver'])
                ->where(function ($query) use ($req_email) {
                    $query->where('email', $req_email)
                        ->orWhere('phone', $req_email);
                })
                ->first();
        } else {
            $user = StoreUser::whereIn('user_type', ['customer'])
                ->where(function ($query) use ($req_email) {
                    $query->where('email', $req_email)
                        ->orWhere('phone', $req_email);
                })
                ->first();
        }
        // if (!$delivery_boy_condition) {
        if (!$delivery_boy_condition && !$seller_condition) {
            if (PayhereUtility::create_wallet_reference($request->identity_matrix) == false) {
                return response()->json(['result' => false, 'message' => 'Identity matrix error', 'user' => null], 401);
            }
        }

        if ($user != null) {
            if (!$user->banned) {
                if (Hash::check($request->password, $user->password)) {
                    if ($request->has('device_token')) {
                        $user->device_token = $request->device_token;
                        $user->save();
                    }
                    if ($request->has('device_key')) {
                        $user->device_key = $request->device_key;
                        $user->save();
                    }
                    // if ($user->email_verified_at == null) {
                    //     return response()->json(['result' => false, 'message' => translate('Please verify your account'), 'user' => null], 401);
                    // }
                    return $this->loginSuccess($user);
                } else {
                    return response()->json(['result' => false, 'message' => translate('Unauthorized'), 'user' => null], 401);
                }
            } else {
                return response()->json(['result' => false, 'message' => translate('User is banned'), 'user' => null], 401);
            }
        } else {
            return response()->json(['result' => false, 'message' => translate('User not found'), 'user' => null], 401);
        }
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {

        $user = request()->user();
        $user->tokens()->where('id', $user->currentAccessToken()->id)->delete();

        return response()->json([
            'result' => true,
            'message' => translate('Successfully logged out')
        ]);
    }

    /**
     * Signs in with a provider's access token.
     *
     * The account is the one the provider returns for that token. It used to
     * be looked up by the `provider` field the client sent as if it were the
     * provider id, so a caller with any valid token of their own could name
     * someone else's provider id and be signed in as them.
     */
    public function socialLogin(Request $request)
    {
        switch ($request->social_provider) {
            case 'facebook':
                $social_user = Socialite::driver('facebook')->fields([
                    'name',
                    'first_name',
                    'last_name',
                    'email'
                ]);
                break;
            case 'google':
                $social_user = Socialite::driver('google')
                    ->scopes(['profile', 'email']);
                break;
            case 'twitter':
                $social_user = Socialite::driver('twitter');
                break;
            case 'apple':
                $social_user = Socialite::driver('sign-in-with-apple')
                    ->scopes(['name', 'email']);
                break;
            default:
                $social_user = null;
        }
        if ($social_user == null) {
            return response()->json(['result' => false, 'message' => translate('No social provider matches'), 'user' => null]);
        }

        if ($request->social_provider == 'twitter') {
            $social_user_details = $social_user->userFromTokenAndSecret($request->access_token, $request->secret_token);
        } else {
            $social_user_details = $social_user->userFromToken($request->access_token);
        }

        if ($social_user_details == null) {
            return response()->json(['result' => false, 'message' => translate('No social account matches'), 'user' => null]);
        }

        $user = app(SocialAccounts::class)->resolve($request->social_provider, $social_user_details, $request->name);

        if ($user === null) {
            return response()->json([
                'result' => false,
                'message' => translate('An account with this email already exists. Sign in with your password instead.'),
                'user' => null
            ], 403);
        }

        if ($user->banned) {
            return response()->json(['result' => false, 'message' => translate('User is banned'), 'user' => null], 401);
        }

        return $this->loginSuccess($user);
    }

    public function loginSuccess($user, $token = null)
    {

        if (!$token) {
            $token = $user->createToken('API Token')->plainTextToken;
        }
        return response()->json([
            'result' => true,
            'message' => translate('Successfully logged in'),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => null,
            'user' => [
                'id' => $user->id,
                'type' => $user->user_type,
                'name' => $user->name,
                'otajer_id' => $user->AccSysID,
                'company_name' => $user->company_name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'avatar_original' => uploaded_asset($user->avatar_original),
                'phone' => $user->phone,
                'is_rep' => $user->is_rep,
                'rep_has_package' => (bool)$user->representativePackage,
                'rep_discount_percentage' => $user->representativePackage ? $user->representativePackage->percentage : 0,
                'company_address' => $user->company_address,
                'shipping_address' => $user->shipping_address,
                'tax_number'    => $user->tax_number,
                'country' => $user->country,
                'trade_license' => $user->trade_license,
                'bank_account_number' => $user->bank_account_number,
                'email_verified' => $user->email_verified_at != null,
                'admin_verified' => $user->admin_verified ? 1 : 0,
            ]
        ]);
    }


    protected function loginFailed()
    {

        return response()->json([
            'result' => false,
            'message' => translate('Login Failed'),
            'access_token' => '',
            'token_type' => '',
            'expires_at' => null,
            'user' => [
                'id' => 0,
                'type' => '',
                'name' => '',
                'email' => '',
                'avatar' => '',
                'avatar_original' => '',
                'phone' => '',
            ]
        ]);
    }


    public function account_deletion()
    {
        if (auth()->user()) {
            Cart::where('user_id', auth()->user()->id)->delete();
        }

        // if (auth()->user()->provider && auth()->user()->provider != 'apple') {
        //     $social_revoke =  new SocialRevoke;
        //     $revoke_output = $social_revoke->apply(auth()->user()->provider);

        //     if ($revoke_output) {
        //     }
        // }

        $auth_user = auth()->user();
        $auth_user->tokens()->where('id', $auth_user->currentAccessToken()->id)->delete();
        $auth_user->customer_products()->delete();

        StoreUser::destroy(auth()->user()->id);

        return response()->json([
            "result" => true,
            "message" => translate('Your account deletion successfully done')
        ]);
    }

    public function getUserInfoByAccessToken(Request $request)
    {
        $token = PersonalAccessToken::findToken((string) $request->access_token);
        if (!$token || $token->isExpired()) {
            return $this->loginFailed();
        }
        $user = $token->tokenable;

        if ($user == null) {
            return $this->loginFailed();
        }
        if ($request->has('device_key')) {
            $user->device_key = $request->device_key;
            $user->save();
        }

        return $this->loginSuccess($user, $request->access_token);
    }
}
