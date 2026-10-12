<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * What a coupon takes off a customer's cart lines.
 *
 * The storefront and the app each carried their own copy: they disagreed on
 * whether an amount coupon is per unit and on what shipping is, and neither
 * stopped an amount coupon from being larger than what it applies to, so a
 * 50-euro coupon on a 5-euro cart made the order total negative.
 */
class CouponService
{
    /**
     * The discount in the order currency, never more than the lines it applies to
     * cost.
     *
     * @param  Collection<int, Cart>  $carts  The signed-in customer's lines from the coupon owner's shop.
     */
    public function discountFor(Coupon $coupon, Collection $carts): float
    {
        $details = json_decode($coupon->details);
        $discount = 0.0;
        $applicable = 0.0;

        if ($coupon->type == 'cart_base') {
            $subtotal = 0;
            $tax = 0;
            $shipping = 0;

            foreach ($carts as $cartItem) {
                $product = Product::find($cartItem['product_id']);
                $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
                $shipping += $cartItem['shipping_cost'];
            }

            $applicable = $subtotal + $tax + $shipping;

            if ($applicable >= $details->min_buy) {
                if ($coupon->discount_type == 'percent') {
                    $discount = min(($applicable * $coupon->discount) / 100, $details->max_discount);
                } elseif ($coupon->discount_type == 'amount') {
                    $discount = (float) $coupon->discount;
                }
            }
        } elseif ($coupon->type == 'product_base') {
            foreach ($carts as $cartItem) {
                $product = Product::find($cartItem['product_id']);
                $line = cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];

                foreach ($details as $detail) {
                    if ($detail->product_id == $cartItem['product_id']) {
                        $applicable += $line;

                        if ($coupon->discount_type == 'percent') {
                            $discount += ($line * $coupon->discount) / 100;
                        } elseif ($coupon->discount_type == 'amount') {
                            $discount += $coupon->discount * $cartItem['quantity'];
                        }
                    }
                }
            }
        }

        return max(0.0, min($discount, $applicable));
    }
}
