<?php

namespace App\Http\Controllers\Api\V2\Seller;

use App\Http\Requests\CouponRequest;
use App\Http\Resources\V2\Seller\CouponResource;
use App\Http\Resources\V2\Seller\ProductCollection;
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
        $coupons = Coupon::where('user_id', auth()->user()->id)->orderBy('id','desc')->get();
        return CouponResource::collection($coupons);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(CouponRequest $request)
    {
        $user_id = auth()->user()->id;
        Coupon::create($request->validated() + [
            'user_id' => $user_id,
        ]);

        return $this->success(__('seller.coupons.coupon_has_been_saved_successfully'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $coupon = Coupon::where('id', $id)->where('user_id', auth()->user()->id)->first();
        // dd($coupon);
        return new CouponResource($coupon);
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
        if (Gate::denies('update', $coupon)) {
            return $this->failed(__('api.seller.coupon_not_owned'));
        }

        // The coupon's own columns only: date_range, min_buy, max_discount and
        // product_ids are inputs CouponRequest folds into start_date,
        // end_date and details, and aren't columns to write.
        $coupon->update($request->safe()->only($coupon->getFillable()));

        return $this->success(__('seller.coupons.coupon_has_been_updated_successfully'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        Coupon::where('id', '=', $id)->where('user_id', auth()->user()->id)->delete();

        return $this->success(__('seller.coupons.coupon_has_been_deleted_successfully'));
    }

    public function coupon_for_product(Request $request)
    {

        if($request->coupon_type == "product_base") {

            $products = Product::with(['category', 'stocks'])->where('name','LIKE',"%".$request->name."%")->where('user_id', auth()->user()->id)->paginate(10);
            // $products = filter_products(Product::where('user_id', auth()->user()->id))->get();
            return new ProductCollection($products);
        }
    }
}
