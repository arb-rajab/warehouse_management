<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductQuery;
use Auth;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductQueryController extends Controller implements HasMiddleware
{
    use ValidatesRequests;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:view_all_product_queries', only: ['index', 'show'])];
    }

    // Retained /temp resource aliases point to the selected admin implementation.
    public function index(): RedirectResponse
    {
        return redirect()->route('product_query.index');
    }

    public function show(string $product_query): RedirectResponse
    {
        try {
            $id = decrypt($product_query);
        } catch (DecryptException) {
            abort(404);
        }
        abort_unless(is_int($id) || (is_string($id) && ctype_digit($id)), 404);
        $query = ProductQuery::query()->findOrFail($id);

        return redirect()->route('product_query.show', $query->id);
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
        flash(__('storefront.support.your_query_has_been_submittes_successfully'))->success();
        return redirect()->back();
    }

}
