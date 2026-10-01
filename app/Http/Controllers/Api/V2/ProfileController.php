<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\AddressCollection;
use App\Services\TradeLicenseStorage;
use App\Http\Resources\V2\CitiesCollection;
use App\Http\Resources\V2\CountriesCollection;
use App\Models\Address;
use App\Models\Cart;
use App\Models\City;
use App\Models\Country;
use App\Models\Order;
use App\Models\StoreUser;
use App\Models\Wishlist;
use App\Notifications\AppEmailVerificationNotification;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Services\Base64ImageUploadService;
use InvalidArgumentException;

class ProfileController extends Controller
{

    // protected function validator(array $data, User $user)
    // {
    //     return Validator::make($data, [
    //         'name' => 'sometimes|required|string|max:255',
    //         'company_name' => 'sometimes|required|string|max:255',
    //         'password' => 'sometimes|required|string|min:6|confirmed',
    //         'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
    //         'tax_number' => 'sometimes|required|max:100',
    //         'bank_account_number' => 'sometimes|required|max:100',
    //         'trade_license' => 'sometimes|required|file|mimes:doc,docx,pdf,png,jpeg,bmb|max:20000',
    //         'country' => 'sometimes|required|string',
    //         'phone' => 'sometimes|required|string',
    //         'company_address' => 'sometimes|required|string',
    //         'shipping_address' => 'sometimes|required|string'
    //     ]);
    // }

    public function counters()
    {
        return response()->json([
            'cart_item_count' => Cart::where('user_id', auth()->user()->id)->count(),
            'wishlist_item_count' => Wishlist::where('user_id', auth()->user()->id)->count(),
            'order_count' => Order::where('user_id', auth()->user()->id)->count(),
        ]);
    }

    public function update(Request $request)
    {
        $user = StoreUser::find(auth()->user()->id);
        if(!$user){
            return response()->json([
                'result' => false,
                'message' => translate("User not found.")
            ]);
        }

        // try {
        //     $validatedData = $this->validator($request->all(), $user)->validate();
        // } catch (ValidationException $e) {
        //     return response()->json([
        //         'result' => false,
        //         'message' => translate('Validation Error'),
        //         'errors' => $e->validator->getMessageBag()->all(),
        //         'user_id' => 0
        //     ], 422);
        // }
        // $user->fill($validatedData);

        if(isset($request->name)){
            $user->name = $request->name;
        }
        if(isset($request->phone)){
            $user->phone = $request->phone;
        }
        if(isset($request->company_name)){
            $user->company_name = $request->company_name;
        }
        if(isset($request->company_address)){
            $user->company_address = $request->company_address;
        }
        if(isset($request->country)){
            $user->country = $request->country;
        }
        if(isset($request->tax_number)){
            $user->tax_number = $request->tax_number;
        }
        if(isset($request->shipping_address)){
            $user->shipping_address = $request->shipping_address;
        }
        if(isset($request->bank_account_number)){
            $user->bank_account_number = $request->bank_account_number;
        }






        if ($request->has('email') &&  $request->email != null) {
            if ($request->email != $user->email) {
                $user->email = $request->email;
                $user->email_verified_at = null;
                $user->verification_code = random_int(100000, 999999);
                try {
                    $user->notify(new AppEmailVerificationNotification());
                } catch (\Exception $e) {
                    return response()->json([
                        'result' => false,
                        'errors' => $e->getMessage(),
                        'message' => translate('Email notification failed'),
                    ], 500);
                }
            }
        }

        if (request()->hasFile('trade_license')) {
            $user->trade_license = app(TradeLicenseStorage::class)->store(request()->file('trade_license'));
        }


        if(isset($request->password)){
        if ($request->password != "") {
            $user->password = Hash::make($request->password);
        }
    }
        $user->save();

        return response()->json([
            'result' => true,
            'message' => translate("Profile information updated"),
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

    public function update_device_token(Request $request)
    {
        $user = StoreUser::find(auth()->user()->id);
        if(!$user){
            return response()->json([
                'result' => false,
                'message' => translate("User not found.")
            ]);
        }

        $user->device_token = $request->device_token;


        $user->save();

        return response()->json([
            'result' => true,
            'message' => translate("device token updated")
        ]);
    }

    public function updateImage(Request $request, Base64ImageUploadService $uploads)
    {
        $user = StoreUser::find(auth()->user()->id);
        if(!$user){
            return response()->json([
                'result' => false,
                'message' => translate("User not found."),
                'path' => ""
            ]);
        }

        try {
            $upload = $uploads->store($request->image, $request->filename, $user->id);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
                'path' => ""
            ]);
        }

        $user->avatar_original = $upload->id;
        $user->save();

        return response()->json([
            'result' => true,
            'message' => translate("Image updated"),
            'path' => uploaded_asset($upload->id)
        ]);
    }

    // not user profile image but any other base 64 image through uploader
    public function imageUpload(Request $request, Base64ImageUploadService $uploads)
    {
        $user = StoreUser::find(auth()->user()->id);
        if(!$user){
            return response()->json([
                'result' => false,
                'message' => translate("User not found."),
                'path' => "",
                'upload_id' => 0
            ]);
        }

        try {
            $upload = $uploads->store($request->image, $request->filename, $user->id);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
                'path' => "",
                'upload_id' => 0
            ]);
        }

        return response()->json([
            'result' => true,
            'message' => translate("Image updated"),
            'path' => uploaded_asset($upload->id),
            'upload_id' => $upload->id
        ]);
    }

    public function checkIfPhoneAndEmailAvailable()
    {


        $phone_available = false;
        $email_available = false;
        $phone_available_message = translate("User phone number not found");
        $email_available_message = translate("User email  not found");

        $user = StoreUser::find(auth()->user()->id);

        if ($user->phone != null || $user->phone != "") {
            $phone_available = true;
            $phone_available_message = translate("User phone number found");
        }

        if ($user->email != null || $user->email != "") {
            $email_available = true;
            $email_available_message = translate("User email found");
        }
        return response()->json(
            [
                'phone_available' => $phone_available,
                'email_available' => $email_available,
                'phone_available_message' => $phone_available_message,
                'email_available_message' => $email_available_message,
            ]
        );
    }
}
