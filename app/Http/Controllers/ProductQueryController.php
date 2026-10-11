<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductQuery;
use Auth;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

class ProductQueryController extends Controller
{
    use ValidatesRequests;

    /**
     * store products queries through the ProductQuery model
     * data comes from product details page
     * authenticated user can leave queries about the product
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'question' => 'required|string',
        ]);
        $product = Product::find($request->product);

        $query = new ProductQuery();
        $query->customer_id = Auth::id();
        $query->seller_id = $product->user_id;
        $query->product_id = $product->id;
        $query->question = $request->question;
        $query->save();
        flash(translate('Your query has been submittes successfully'))->success();
        return redirect()->back();
    }

}
