<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductQuery;
use App\Models\StoreUser;
use Auth;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductQueryController extends Controller implements HasMiddleware
{
    use ValidatesRequests;

    /**
     * Get the middleware that should be assigned to the controller.
     *
     * See Auth\LoginController::middleware()'s docblock for why this
     * replaces the constructor-based $this->middleware() call.
     *
     * @return array<int, \Illuminate\Routing\Controllers\Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_product_queries', only: ['admin_index']),
        ];
    }
    /**
     * Retrieve queries that belongs to current seller
     */
    public function index()
    {
        $admin_id = StoreUser::where('user_type','admin')->first()->id;
        $queries = ProductQuery::where('seller_id', $admin_id)->latest()->paginate(20);
        return view('shop.backend.support.product_query.index', compact('queries'));
    }

    /**
     * Retrieve specific query using query id.
     */
    public function show($id)
    {
        $query = ProductQuery::find(decrypt($id));
        return view('shop.backend.support.product_query.show', compact('query'));
    }

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

    /**
     * Store reply against the question from Admin panel
     */

    public function reply(Request $request, $id)
    {
        $this->validate($request, [
            'reply' => 'required',
        ]);
        $query = ProductQuery::find($id);
        $query->reply = $request->reply;
        $query->save();
        flash(translate('Replied successfully!'))->success();
        return redirect()->route('product_query.index');
    }
}
