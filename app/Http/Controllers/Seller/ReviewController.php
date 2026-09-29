<?php

namespace App\Http\Controllers\Seller;

use App\Models\Review;
use Auth;
use DB;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $reviews = DB::table('reviews')
                    ->orderBy('id', 'desc')
                    ->join('products', 'reviews.product_id', '=', 'products.id')
                    ->where('products.user_id', Auth::user()->id)
                    ->select('reviews.id')
                    ->distinct()
                    ->paginate(9);

        foreach ($reviews as $key => $value) {
            $review = Review::find($value->id);
            $review->viewed = 1;
            $review->save();
        }

        return view('seller.reviews', compact('reviews'));
    }

}
