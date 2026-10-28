<?php

namespace App\Http\Controllers;

use App\Models\FollowSeller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class FollowSellerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $followed_sellers = FollowSeller::where('user_id', Auth::user()->id)->orderBy('shop_id', 'asc')->paginate(10);
        return view('shop.frontend.user.customer.followed_sellers', compact('followed_sellers'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        if(isCustomer()){
            $followed_seller = FollowSeller::where('user_id', Auth::user()->id)->where('shop_id', $request->id)->first();
            if($followed_seller == null){
                FollowSeller::insert([
                    'user_id' => Auth::user()->id,
                    'shop_id' => $request->id
                ]);
            }
            flash(__('storefront.product.seller_followed'))->success();
            return back();
        }
        flash(__('storefront.product.follow_login_required'))->success();
        return back();
    }

    public function remove(Request $request)
    {
        $followed_seller = FollowSeller::where('user_id', Auth::user()->id)->where('shop_id', $request->id)->first();
        if($followed_seller!=null){
            FollowSeller::where('user_id', Auth::user()->id)->where('shop_id', $request->id)->delete();
            flash(__('storefront.product.seller_unfollowed'))->success();
            return back();
        }
    }
}
