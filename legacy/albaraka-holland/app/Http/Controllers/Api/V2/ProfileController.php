<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\City;
use App\Models\Country;
use App\Http\Resources\V2\AddressCollection;
use App\Models\Address;
use App\Http\Resources\V2\CitiesCollection;
use App\Http\Resources\V2\CountriesCollection;
use App\Models\Order;
use App\Models\Upload;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use App\Models\Cart;
use Hash;
use Illuminate\Support\Facades\File;
use Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Notifications\AppEmailVerificationNotification;

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
        $user = User::find(auth()->user()->id);
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
                $user->verification_code = rand(100000, 999999);
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
            $uploadedFile = request()->file('trade_license');
            $pdfFileName = $uploadedFile->getClientOriginalName(); // Get the original filename

            // Store the PDF file in the 'local' disk with the specified filename
            $pdfPath = $uploadedFile->storeAs('uploads/licenses', $pdfFileName, 'local');

            $user->trade_license = $pdfFileName; // Store the path in your database
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
                'admin_verified' => $user->admin_verified ? 1 : 0
            ]
        ]);
    }

    public function update_device_token(Request $request)
    {
        $user = User::find(auth()->user()->id);
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

    public function updateImage(Request $request)
    {
        $user = User::find(auth()->user()->id);
        if(!$user){
            return response()->json([
                'result' => false,
                'message' => translate("User not found."),
                'path' => ""
            ]);
        }

        $type = array(
            "jpg" => "image",
            "jpeg" => "image",
            "png" => "image",
            "svg" => "image",
            "webp" => "image",
            "gif" => "image",
        );

        try {
            $image = $request->image;
            $request->filename;
            $realImage = base64_decode($image);

            $dir = public_path('uploads/all');
            $full_path = "$dir/$request->filename";

            $file_put = file_put_contents($full_path, $realImage); // int or false

            if ($file_put == false) {
                return response()->json([
                    'result' => false,
                    'message' => "File uploading error",
                    'path' => ""
                ]);
            }


            $upload = new Upload;
            $extension = strtolower(File::extension($full_path));
            $size = File::size($full_path);

            if (!isset($type[$extension])) {
                unlink($full_path);
                return response()->json([
                    'result' => false,
                    'message' => "Only image can be uploaded",
                    'path' => ""
                ]);
            }


            $upload->file_original_name = null;
            $arr = explode('.', File::name($full_path));
            for ($i = 0; $i < count($arr) - 1; $i++) {
                if ($i == 0) {
                    $upload->file_original_name .= $arr[$i];
                } else {
                    $upload->file_original_name .= "." . $arr[$i];
                }
            }

            //unlink and upload again with new name
            unlink($full_path);
            $newFileName = rand(10000000000, 9999999999) . date("YmdHis") . "." . $extension;
            $newFullPath = "$dir/$newFileName";

            $file_put = file_put_contents($newFullPath, $realImage);

            if ($file_put == false) {
                return response()->json([
                    'result' => false,
                    'message' => "Uploading error",
                    'path' => ""
                ]);
            }

            $newPath = "uploads/all/$newFileName";

            if (env('FILESYSTEM_DRIVER') == 's3') {
                Storage::disk('s3')->put($newPath, file_get_contents(base_path('public/') . $newPath),
                ['visibility' => 'public']
            );
                unlink(base_path('public/') . $newPath);
            }

            $upload->extension = $extension;
            $upload->file_name = $newPath;
            $upload->user_id = $user->id;
            $upload->type = $type[$upload->extension];
            $upload->file_size = $size;
            $upload->save();

            $user->avatar_original = $upload->id;
            $user->save();



            return response()->json([
                'result' => true,
                'message' => translate("Image updated"),
                'path' => uploaded_asset($upload->id)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
                'path' => ""
            ]);
        }
    }

    // not user profile image but any other base 64 image through uploader
    public function imageUpload(Request $request)
    {
        $user = User::find(auth()->user()->id);
        if(!$user){
            return response()->json([
                'result' => false,
                'message' => translate("User not found."),
                'path' => "",
                'upload_id' => 0
            ]);
        }

        $type = array(
            "jpg" => "image",
            "jpeg" => "image",
            "png" => "image",
            "svg" => "image",
            "webp" => "image",
            "gif" => "image",
        );

        try {
            $image = $request->image;
            $request->filename;
            $realImage = base64_decode($image);

            $dir = public_path('uploads/all');
            $full_path = "$dir/$request->filename";

            $file_put = file_put_contents($full_path, $realImage); // int or false

            if ($file_put == false) {
                return response()->json([
                    'result' => false,
                    'message' => "File uploading error",
                    'path' => "",
                    'upload_id' => 0
                ]);
            }


            $upload = new Upload;
            $extension = strtolower(File::extension($full_path));
            $size = File::size($full_path);

            if (!isset($type[$extension])) {
                unlink($full_path);
                return response()->json([
                    'result' => false,
                    'message' => "Only image can be uploaded",
                    'path' => "",
                    'upload_id' => 0
                ]);
            }


            $upload->file_original_name = null;
            $arr = explode('.', File::name($full_path));
            for ($i = 0; $i < count($arr) - 1; $i++) {
                if ($i == 0) {
                    $upload->file_original_name .= $arr[$i];
                } else {
                    $upload->file_original_name .= "." . $arr[$i];
                }
            }

            //unlink and upload again with new name
            unlink($full_path);
            $newFileName = rand(10000000000, 9999999999) . date("YmdHis") . "." . $extension;
            $newFullPath = "$dir/$newFileName";

            $file_put = file_put_contents($newFullPath, $realImage);

            if ($file_put == false) {
                return response()->json([
                    'result' => false,
                    'message' => "Uploading error",
                    'path' => "",
                    'upload_id' => 0
                ]);
            }

            $newPath = "uploads/all/$newFileName";

            if (env('FILESYSTEM_DRIVER') == 's3') {
                Storage::disk('s3')->put($newPath, file_get_contents(base_path('public/') . $newPath));
                unlink(base_path('public/') . $newPath);
            }

            $upload->extension = $extension;
            $upload->file_name = $newPath;
            $upload->user_id = $user->id;
            $upload->type = $type[$upload->extension];
            $upload->file_size = $size;
            $upload->save();

            return response()->json([
                'result' => true,
                'message' => translate("Image updated"),
                'path' => uploaded_asset($upload->id),
                'upload_id' => $upload->id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
                'path' => "",
                'upload_id' => 0
            ]);
        }
    }

    public function checkIfPhoneAndEmailAvailable()
    {


        $phone_available = false;
        $email_available = false;
        $phone_available_message = translate("User phone number not found");
        $email_available_message = translate("User email  not found");

        $user = User::find(auth()->user()->id);

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
