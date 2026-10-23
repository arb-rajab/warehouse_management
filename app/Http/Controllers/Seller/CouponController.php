<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\CouponRequest;
use App\Models\Coupon;
use App\Models\Product;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $coupons = Coupon::where('user_id', Auth::user()->id)->orderBy('id','desc')->get();
        return view('shop.seller.coupons.index', compact('coupons'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $products = $this->couponProducts();
        return view('shop.seller.coupons.create', compact('products'));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(CouponRequest $request)
    {
        $user_id = Auth::user()->id;
        Coupon::create($request->couponAttributes() + [
            'user_id' => $user_id,
        ]);

        flash(translate('Coupon has been saved successfully.'))->success();
        return redirect()->route('seller.coupon.index');
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
        $coupon = Coupon::findOrFail(decrypt($id));
        Gate::authorize('update', $coupon);
        $products = $this->couponProducts();
        return view('shop.seller.coupons.edit', compact('coupon', 'products'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(CouponRequest $request, Coupon $coupon)
    {
        Gate::authorize('update', $coupon);
        $coupon->update($request->couponAttributes());

        flash(translate('Coupon has been updated successfully'))->success();
        return redirect()->route('seller.coupon.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $coupon = Coupon::findOrFail($id);
        Gate::authorize('delete', $coupon);
        $coupon->delete();
        flash(translate('Coupon has been deleted successfully'))->success();
        return redirect()->route('seller.coupon.index');
    }

    public function get_coupon_form(Request $request)
    {
        if($request->coupon_type == "product_base") {
            $products = $this->couponProducts();
            return view('shop.seller.coupons.fields', ['products' => $products, 'type' => 'product_base']);
        }
        elseif($request->coupon_type == "cart_base"){
            return view('shop.seller.coupons.fields', ['type' => 'cart_base']);
        }
    }

    public function get_coupon_form_edit(Request $request)
    {
        if($request->coupon_type == "product_base") {
            $coupon = Coupon::findOrFail($request->id);
            Gate::authorize('update', $coupon);
            $products = $this->couponProducts();
            return view('shop.seller.coupons.fields', ['coupon' => $coupon, 'products' => $products, 'type' => 'product_base']);
        }
        elseif($request->coupon_type == "cart_base"){
            $coupon = Coupon::findOrFail($request->id);
            Gate::authorize('update', $coupon);
            return view('shop.seller.coupons.fields', ['coupon' => $coupon, 'type' => 'cart_base']);
        }
    }
    /** @return \Illuminate\Database\Eloquent\Collection<int, Product> */
    private function couponProducts(): \Illuminate\Database\Eloquent\Collection
    {
        return filter_products(Product::where('user_id', Auth::user()->id))
            ->select(['id', 'name'])
            ->with('product_translations:id,product_id,lang,name')
            ->get();
    }

}
