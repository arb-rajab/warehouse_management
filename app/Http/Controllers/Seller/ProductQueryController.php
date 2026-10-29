<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ReplyProductQueryRequest;
use App\Models\ProductQuery;
use Auth;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

class ProductQueryController extends Controller
{
    use ValidatesRequests;

    /**
     * Retrieve queries that belongs to current seller
     */
    public function index()
    {
        $queries = ProductQuery::where('seller_id', Auth::id())->latest()->paginate(20);
        return view('shop.seller.product_query.index', compact('queries'));
    }
    /**
     * Retrieve specific query using query id.
     */
    public function show($id)
    {
        $query = ProductQuery::where('seller_id', Auth::id())->findOrFail(decrypt($id));
        return view('shop.seller.product_query.show', compact('query'));
    }
    /**
     * Store reply against the question from seller panel
     */

    public function reply(ReplyProductQueryRequest $request, $id)
    {
        $query = ProductQuery::where('seller_id', Auth::id())->findOrFail($id);
        $query->reply = $request->reply;
        $query->save();
        flash(__('seller.products.replied_successfully'))->success();
        return redirect()->route('seller.product_query.index');
    }
}
