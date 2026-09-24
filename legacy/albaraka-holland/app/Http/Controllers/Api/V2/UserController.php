<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\UserCollection;
use App\Models\User;
use Illuminate\Http\Request;

use Laravel\Sanctum\PersonalAccessToken;


class UserController extends Controller
{
    public function info($id)
    {
        return new UserCollection(User::where('id', auth()->user()->id)->get());
    }

    public function updateName(Request $request)
    {
        $user = User::findOrFail($request->user_id);
        $user->update([
            'name' => $request->name
        ]);
        return response()->json([
            'message' => translate('Profile information has been updated successfully')
        ]);
    }

    public function getUserInfoByAccessToken(Request $request)
    {

        $false_response = [
            'result' => false,
            'id' => 0,
            'name' => "",
            'email' => "",
            'avatar' => "",
            'avatar_original' => "",
            'phone' => "",
            'admin_verified' => 0,
            'is_rep' => 0
        ];



        $token = PersonalAccessToken::findToken($request->access_token);
        if (!$token) {
            return response()->json($false_response);
        }

        $user = $token->tokenable;



        if ($user == null) {
            return response()->json($false_response);

        }

        return response()->json([
            'result' => true,
            'is_rep' => $user->is_rep,
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'avatar_original' => uploaded_asset($user->avatar_original),
            'phone' => $user->phone,
            'admin_verified' => $user->admin_verified
        ]);

    }

    // check if the customer is verified by admin
    public function check_verified_by_admin(Request $request){
        $false_response = [
            'verified_by_admin' => false,
        ];

        $user = User::find($request->user_id);
        if($user != null){
            if($user->admin_verified == true){
                return response()->json([
                    'verified_by_admin' => true
                ]);
            }
            else{
                return response()->json($false_response);
            }
        }
        else{
            return response()->json($false_response);
        }
    }
}
