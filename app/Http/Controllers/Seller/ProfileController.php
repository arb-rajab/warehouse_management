<?php

namespace App\Http\Controllers\Seller;

use App\Http\Requests\SellerProfileRequest;
use App\Models\StoreUser;
use Auth;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $user = Auth::user();
        $addresses = $user->addresses;
        return view('shop.seller.profile.index', compact('user','addresses'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(SellerProfileRequest $request , $id)
    {
        if(env('DEMO_MODE') == 'On'){
            flash(__('storefront.common.sorry_the_action_is_not_permitted_in_demo'))->error();
            return back();
        }

        // The seller's own account only: this took any account by id,
        // its password included.
        $user = StoreUser::findOrFail($id);
        abort_unless($user->is(Auth::user()), 403);
        $user->name = $request->name;
        $user->phone = $request->phone;

        if($request->new_password != null && ($request->new_password == $request->confirm_password)){
            $user->password = Hash::make($request->new_password);
        }

        $user->avatar_original = $request->photo;

        $shop = $user->shop;

        if($shop){
            $shop->cash_on_delivery_status = (int) $request->boolean('cash_on_delivery_status');
            $shop->bank_payment_status = (int) $request->boolean('bank_payment_status');
            $shop->bank_name = $request->bank_name;
            $shop->bank_acc_name = $request->bank_acc_name;
            $shop->bank_acc_no = $request->bank_acc_no;
            $shop->bank_routing_no = $request->bank_routing_no;

            $shop->save();
        }

        $user->save();

        flash(__('storefront.upload.your_profile_has_been_updated_successfully'))->success();
        return back();
    }
}
