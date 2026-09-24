<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\CustomerCollection;
use App\Http\Resources\V2\Warehouse\CustomerDetailsResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Cart;
use App\Models\User;
use App\Models\Country;
use App\Models\Address;
use Auth;
use App\Notifications\AppEmailVerificationNotification;
use App\Http\Controllers\OTPVerificationController;
use App\Models\BusinessSetting;
use App\Http\Controllers\Auth\RegisterController;
use App\Services\UserService;
use App\Models\CartOffered;
use App\Models\Visit;
use Carbon\Carbon;

class CustomerController extends Controller
{
    protected $userService;

    protected function userService()
    {
        return app(UserService::class);
    }

    public function show()
    {
        $user = User::where("id", auth()->user()->id)->where("user_type", "customer")->get();

        return new CustomerCollection($user);
    }

    public function get_customers(Request $request)
    {
        if (!auth()->user()->is_rep) {
            return response()->json([
                'error' => 'You are not representative',
                'status' => 0
            ], 403);
        }

        $users = User::where('id', '!=', auth()->user()->id)
            ->where('is_rep', false)
            ->where('user_type', 'customer')
            ->orderByRaw('is_important DESC, id ASC');

        if ($request->name !== "" && $request->name !== null) {
            $users->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('otajer_id')) {
            $users->where('AccSysID', 'like', '%' . $request->otajer_id . '%');
        }

        if ($request->filled('company_name')) {
            $users->where('company_name', 'like', '%' . $request->company_name . '%');
        }

        $data = [
            'data' => $users->select('id', 'name', 'AccSysID as otajerID', 'company_name')->get()
        ];

        return $data;
    }


    public function get_rep_current_customer()
    {
        if (!auth()->user()->is_rep) {
            return response()->json([
                'error' => 'You are not representative',
                'status' => 0
            ], 403);
        }
        $current_customer = Auth::user()->current_customer;

        if (!$current_customer) {
            return response()->json([
                'message' => 'You did not choose a user for your cart yet.',
                'status' => 0
            ], 404);
        }

        return new CustomerDetailsResource($current_customer);
    }

    public function pick_customer(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => "Inputs Validation Error",
                'errors' => $validator->errors()
            ]);
        }

        $chosenUser = User::find($request->id);

        if (!$chosenUser) {
            return response()->json([
                'rep_id' => 0,
                'message' => 'User not found',
                'result' => false
            ], 404);
        }

        if (!$user->is_rep) {
            return response()->json([
                'rep_id' => 0,
                'message' => 'You cannot do this action, you are not representative',
                'result' => false
            ], 403);
        }

        if ($chosenUser->is_rep) {
            return response()->json([
                'rep_id' => 0,
                'message' => 'You cannot do this action, you cannot choose another representative',
                'result' => false
            ], 403);
        }

        if ($chosenUser->user_type != 'customer') {
            return response()->json([
                'rep_id' => 0,
                'message' => 'You cannot do this action, you cannot choose this user',
                'result' => false
            ], 403);
        }

        $open_visit = [];
        $open_visit['has_open_visit'] = false;
        $open_visit['visit_id'] = null;

        if (auth()->user()->rep_last_visit() && auth()->user()->rep_last_visit()->order_id == null) {
            if (auth()->user()->rep_last_visit()->customer_id != $chosenUser->id) {
                $open_visit['has_open_visit'] = true;
                $open_visit['visit_id'] = auth()->user()->rep_last_visit()->id;
                Visit::create([
                    'rep_id' => $user->id,
                    'customer_id' => $chosenUser->id,
                    'rank' => auth()->user()->rep_last_visit_of_the_day()?->rank + 1 ?? 0,
                    'map_lat' => $request->latitude,
                    'map_lng' => $request->longitude,
                    'visit_date' => now(),
                ]);
            }
        } else {
            if (auth()->user()->rep_last_visit_of_the_day()) {
                $rank = auth()->user()->rep_last_visit_of_the_day()?->rank + 1;
            } else {
                $rank = 0;
            }
            Visit::create([
                'rep_id' => $user->id,
                'customer_id' => $chosenUser->id,
                'rank' => $rank,
                'map_lat' => $request->latitude,
                'map_lng' => $request->longitude,
                'visit_date' => now(),
            ]);
        }
        if ($user->carts && $user->carts->count() > 0) {
            $user->carts->each(function ($cart) use ($chosenUser) {
                if ($cart->for_customer != $chosenUser->id) {
                    $cart->delete();
                }
            });
        }
        if ($user->carts_offered && $user->carts_offered->count() > 0) {
            $user->carts_offered->each(function ($cart_offered) use ($chosenUser) {
                if ($cart_offered->for_customer != $chosenUser->id) {
                    $cart_offered->delete();
                }
            });
        }

        return response()->json([
            'rep_id' => $user->id,
            'message' => 'The user has been chosen successfully',
            'data' => new CustomerDetailsResource($chosenUser),
            'result' => true,
            'open_visit' => $open_visit
        ], 200);
    }

    public function create_customer(Request $request)
    {
        $messages = array(
            'name.required' => translate('Name is required'),
            'email_or_phone.required' => $request->register_by == 'email' ? translate('Email is required') : translate('Phone is required'),
            'email_or_phone.email' => translate('Email must be a valid email address'),
            'email_or_phone.numeric' => translate('Phone must be a number.'),
            'email_or_phone.unique' => $request->register_by == 'email' ? translate('The email has already been taken') : translate('The phone has already been taken'),
            'password.required' => translate('Password is required'),
            'password.confirmed' => translate('Password confirmation does not match'),
            'password.min' => translate('Minimum 6 digits required for password')
        );
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
            'email_or_phone' => 'required|email|unique:users,email',
            'country' => 'required|string',
            'phone' => 'required|string',

        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => $validator->errors()->all()
            ]);
        }

        $user = new User();
        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->company_name = $request->company_name;
        //$user->company_address = $request->company_address;
        //$user->shipping_address = $request->shipping_address;
        //$user->tax_number = $request->tax_number;
        //$user->bank_account_number =  $request->bank_account_number;
        $user->country = $request->country;
        $user->verification_code = rand(100000, 999999);
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

        $user->email_verified_at = now();

        $user->save();

        $this->userService()->createCustomerOnOtajer($user); // to create an account for the user in otajer store.

        // Automatically create an address for the user
        $country_id = Country::where('name', $request->country)->value('id');
        $address = new Address;
        $address->user_id       = $user->id;
        //$address->address       = $request->shipping_address;
        $address->country_id    = $country_id;
        $address->phone         = $request->phone;
        $address->set_default   = 1;
        $address->save();

        return response()->json([
            'result' => true,
            'message' => translate('Customer Account Successfully Created.'),
            'access_token' => '',
            'token_type' => '',
            'expires_at' => null,
            'user' => [
                'id' => $user->id,
                'type' => $user->user_type,
                'name' => $user->name,
                'company_name' => $user->company_name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'avatar_original' => uploaded_asset($user->avatar_original),
                'phone' => $user->phone,
                'is_rep' => $user->is_rep,
                'company_address' => $user->company_address,
                'shipping_address' => $user->shipping_address,
                'tax_number'    => $user->tax_number,
                'country' => $user->country,
                'trade_license' => $user->trade_license,
                'bank_account_number' => $user->bank_account_number,
                'email_verified' => $user->email_verified_at != null,
                'admin_verified' => $user->admin_verified ? 1 : 0
            ]
        ]);
    }

    public function visit_note(Request $request)
    {
        $visit = Visit::find($request->visit_id);
        if ($visit) {
            $visit->note = $request->note ?? 'Representative did not add a note for changing the customer';
            $visit->save();

            return response()->json([
                'message' => 'Visit note has been added successfully',
                'data' => $visit,
                'result' => true,
            ], 200);
        }

        return response()->json([
            'message' => 'Visit not found',
            'result' => false
        ], 404);
    }

    public function current_visit(Request $request)
    {
        if (auth()->user()->has_open_visit()) {
            $last_visit = auth()->user()->rep_last_open_visit();
            return response()->json([
                'message' => '',
                'data' => $last_visit->load(['customer' => function ($query) {
                    $query->select('id', 'name', 'company_name'); // Use the id for the relation mapping
                }]),
                'result' => true,
            ], 200);
        }

        return response()->json([
            'message' => 'Rep did not make a visit yet',
            'result' => false
        ], 404);
    }
}
