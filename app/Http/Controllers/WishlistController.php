<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WishlistController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $wishlists = Wishlist::with(['product' => fn ($product) => $product->with(Product::WITH_STORE_LIST)])->where('user_id', Auth::user()->id)->paginate(15);
        return view('shop.frontend.user.view_wishlist', compact('wishlists'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        if(Auth::check()){
            $wishlist = Wishlist::where('user_id', Auth::user()->id)->where('product_id', $request->id)->first();
            if($wishlist == null){
                $wishlist = new Wishlist;
                $wishlist->user_id = Auth::user()->id;
                $wishlist->product_id = $request->id;
                $wishlist->save();
            }
            return view('shop.frontend.partials.wishlist');
        }
        return 0;
    }

    public function remove(Request $request)
    {
        // The customer's own wishlist item only: this deleted any by id.
        $wishlist = Wishlist::where('user_id', Auth::user()->id)->findOrFail($request->id);
        if($wishlist->delete()){
            return view('shop.frontend.partials.wishlist');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
