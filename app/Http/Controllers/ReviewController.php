<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReviewController extends Controller
{
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
        $request->validate(['rating' => 'required|numeric|between:1,5']);

        $product = Product::findOrFail($request->product_id);

        if (! $product->isReviewableBy(Auth::user()->id)) {
            flash(__('storefront.product.you_cannot_review_this_product'))->error();
            return back();
        }

        $review = new Review;
        $review->product_id = $product->id;
        $review->user_id = Auth::user()->id;
        $review->rating = $request->rating;
        $review->comment = $request->comment;
        $review->photos = implode(',', (array) $request->photos);
        $review->viewed = '0';
        $review->save();
        if(Review::where('product_id', $product->id)->where('status', 1)->count() > 0){
            $product->rating = Review::where('product_id', $product->id)->where('status', 1)->sum('rating')/Review::where('product_id', $product->id)->where('status', 1)->count();
        }
        else {
            $product->rating = 0;
        }
        $product->save();

        if($product->added_by == 'seller'){
            $seller = $product->user->shop;
            $seller->rating = (($seller->rating*$seller->num_of_reviews)+$review->rating)/($seller->num_of_reviews + 1);
            $seller->num_of_reviews += 1;
            $seller->save();
        }

        flash(__('storefront.product.review_has_been_submitted_successfully'))->success();
        return back();
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

    public function product_review_modal(Request $request){
        $product = Product::where('id',$request->product_id)->first();
        $review = Review::where('user_id',Auth::user()->id)->where('product_id',$product->id)->first();
        return view('shop.frontend.user.product_review_modal', compact('product','review'));
    }
}
