<?php


namespace App\Http\Controllers\Api\V2;


use App\Services\CouponService;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Product;
use Illuminate\Http\Request;

class CheckoutController
{
    public function apply_coupon_code(Request $request)
    {
        $coupon = Coupon::where('code', $request->coupon_code)->first();

        if ($coupon == null) {
            return response()->json([
                'result' => false,
                'message' => translate('Invalid coupon code!')
            ]);
        }

        $cart_items = Cart::where('user_id', auth()->user()->id)->where('owner_id', $coupon->user_id)->get();

        if ($cart_items->isEmpty()) {
            return response()->json([
                'result' => false,
                'message' => translate('This coupon is not applicable to your cart products!')
            ]);
        }

        $in_range = strtotime(date('d-m-Y')) >= $coupon->start_date && strtotime(date('d-m-Y')) <= $coupon->end_date;

        if (!$in_range) {
            return response()->json([
                'result' => false,
                'message' => translate('Coupon expired!')
            ]);
        }

        $is_used = CouponUsage::where('user_id', auth()->user()->id)->where('coupon_id', $coupon->id)->first() != null;

        if ($is_used) {
            return response()->json([
                'result' => false,
                'message' => translate('You already used this coupon!')
            ]);
        }

        $coupon_discount = app(CouponService::class)->discountFor($coupon, $cart_items);

        if($coupon_discount>0){
            Cart::where('user_id', auth()->user()->id)->where('owner_id', $coupon->user_id)->update([
                'discount' => $coupon_discount / count($cart_items),
                'coupon_code' => $request->coupon_code,
                'coupon_applied' => 1
            ]);

            return response()->json([
                'result' => true,
                'message' => translate('Coupon Applied')
            ]);
        }else{
            return response()->json([
                'result' => false,
                'message' => translate('This coupon is not applicable to your cart products!')
            ]);
        }

    }


    public function remove_coupon_code(Request $request)
    {
        Cart::where('user_id', auth()->user()->id)->update([
            'discount' => 0.00,
            'coupon_code' => "",
            'coupon_applied' => 0
        ]);

        return response()->json([
            'result' => true,
            'message' => translate('Coupon Removed')
        ]);
    }
}
