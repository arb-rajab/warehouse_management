<?php
namespace App\Services;

use App\Models\Cart;
use App\Models\CartOffered;
use App\Models\Offer;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class OfferService
{

    protected function process_brand_offer($offer, $order_detail, $order){
        foreach($offer->offer_products as $offer_product){
            $offer_order_details = new OrderDetail();

            // Assign the properties from $order_detail to $offer_order_details
            $offer_order_details = $order_detail->replicate();

            $offer_order_details->order_id = $order->id;
            $offer_order_details->seller_id = $offer_product->product->user_id;
            $offer_order_details->product_serial = $offer_product->product->serial;
            $offer_order_details->product_matId = $offer_product->product->mat_id;
            $offer_order_details->product_name = $offer_product->product->ar_name;
            $offer_order_details->product_id = $offer_product->product->id;
            $offer_order_details->variation = 'PerBox';
            $offer_order_details->variation_qty = null;
            $offer_order_details->price =  offer_discount($offer_product, false) * $offer_product->quantity;
            $offer_order_details->original_price =  $offer_order_details->price;
            $offer_order_details->product_serial = $offer_product->product->serial;
            $offer_order_details->tax = $offer_product->product->tax;
            $offer_order_details->item_notes = 'Got this product from the ' . $offer->title . ' offer';

            $offer_order_details->quantity = $offer_product->quantity;
            $offer_order_details->save();
        }
    }

    protected function createCartOffer($data, $offer_product, $repeatable_quantity, $offer, $user){
        try{
            $offer_data = is_array($data) ? $data : $data->toArray();

            $offer_data['price'] = offer_discount($offer_product, false);
            $offer_data['product_id'] = $offer_product->product_id;
            $offer_data['quantity'] = $offer_product->quantity * (int)$repeatable_quantity;
            $offer_data['offer_id'] = $offer->id;
            $offer_data['item_notes'] = 'Got this product from the ' . $offer->title . ' offer';
            $offer_data['variation_qty'] = '[]';

            // Remove the 'discount' column if it exists in the $offer_data array
            unset($offer_data['discount']);

            $cart_offered = CartOffered::create($offer_data);
            $user->offers()->sync($offer->id, ['created_at' => now(), 'updated_at' => now()]);
            $user->offers()->updateExistingPivot($offer->id, ['created_at' => now(), 'updated_at' => now()]);
            return 1;
        }catch(\Exception $e){
            return 0;
        }
    }

    protected function checkOfferInCart(User $user, Offer $offer)
    {
        if ($user->got_offer($offer->id) && $user->carts_offered->isNotEmpty()) {
            // Check if any offer product exists in the user's cart
            $hasOfferProduct = $user->carts_offered->contains(function ($cartProduct) use ($offer) {
                return $offer->offer_products->pluck('product_id')->contains($cartProduct->product_id);
            });

            if ($hasOfferProduct) {
                return 1;
            }
        }

        return 0;
    }

    public function processOffer($product, $data, $product_quantity_in_cart = null, $request = null)
    {
        $offer_message = null;
        $user = Auth::user()->load('offers');

        if ($product->offers?->count() > 0) {
            $offer = $product->offers->toQuery()->active()->first();

            if($offer && $offer->brand_id){
                return $offer_message;
            }

            if ($offer && (!$offer->user_got_offer() || $offer->multiple_usage)) {
                $offer_base_product = $offer->base_product->load('product');
                $offer_products = $offer->offer_products->load('product');
                $quantity = $product_quantity_in_cart ?? $request->quantity;
                $repeatable_quantity = 1;

                if ($offer->is_repeatable) {
                    $repeatable_quantity = (int)$quantity / $offer_base_product->quantity;
                }

                if ($offer_base_product->quantity <= $quantity) {
                    foreach ($offer_products as $offer_product) {
                        if (!$user->offers->where('id', $offer->id)->first()) {

                            $is_offer_created = $this->createCartOffer($data, $offer_product, $repeatable_quantity, $offer, $user);
                            if (!$is_offer_created) {
                                $offer_message = translate('You did not get the offer, something went wrong');
                                return $offer_message;
                            }
                        } else {
                            $cart_offer_item = $user->carts_offered->where('offer_id', $offer->id)->where('product_id', $offer_product->product_id)->first();

                            // if the user got the offer but the offer type is multiple usage
                            if($cart_offer_item == null){
                                $is_offer_created = $this->createCartOffer($data, $offer_product, $repeatable_quantity, $offer, $user);
                                if (!$is_offer_created) {
                                    $offer_message = translate('You did not get the offer, something went wrong');
                                    return $offer_message;
                                }
                            }else{
                                $cart_offer_item->quantity = $offer_product->quantity * (int)$repeatable_quantity;
                                $cart_offer_item->save();
                                $user->offers()->updateExistingPivot($offer->id, ['updated_at' => now()]);
                            }
                        }
                    }
                    $offer_message = translate('Congratulation!, the offered products has been added to your cart!');
                } else {
                    $offer_message = translate('You did not get the offer, you need to add more quantity of the product');
                }
            } else {
                // to delete the cart offered if the new quantity is less than the required quantity
                if($offer && $this->checkOfferInCart($user, $offer)){
                    foreach($user->carts_offered->where('offer_id', $offer->id) as $cart_to_delete){
                        $cart_to_delete->forceDelete();
                    }
                    $user->offers()->detach($offer->id);
                }
                $offer_message = '';
            }
        }

        return $offer_message;
    }

    public function removeProductOfferFromCart($cart_id)
    {
        $user = Auth::user();
        $cart_to_delete = Cart::find($cart_id);
        $carts = Cart::where('user_id', $user->id)->get()->load('product', 'product.offers');
        foreach($carts as $cart){
            $offer_of_cart_product = $cart->product->offers?->first();
            if($cart_to_delete && $cart_to_delete->product_id == $offer_of_cart_product?->base_product?->product_id){
                $offer_of_cart_product?->offer_products->pluck('id')->toArray();
                $offer_carts_ids_to_delete = $offer_of_cart_product?->offer_products->pluck('product_id')->toArray();
                $offer_carts_to_delete = $user->carts_offered?->whereIn('product_id' , $offer_carts_ids_to_delete);
                foreach($offer_carts_to_delete as $cart_to_delete){
                    $cart_to_delete->forceDelete();
                }
                $user->offers()->detach($offer_of_cart_product->id);
                break;
            }
        }
    }

    public function processOfferOnCheckOut($order_detail, $order, $subtotal, $product_variation)
    {
        $user = Auth::user();
        $carts = Cart::where('user_id', $user->id)->get();
        $brand_offers = Offer::where('offer_type', 'brand_offer')->active()->get();
        $brandTotals = []; // Initialize an associative array to store brand totals

        if($brand_offers->count() > 0){
            foreach ($carts as $cart) {
                $brand = $cart->product->brand;
                if ($brand) {

                    // Calculate the cart price
                    $cartPrice = $cart->price * $cart->quantity;

                    // Update brand total using the brand ID as key
                    $brandId = $brand->id;
                    if (!isset($brandTotals[$brandId])) {
                        $brandTotals[$brandId] = $cartPrice;
                    } else {
                        $brandTotals[$brandId] += $cartPrice;
                    }
                }
            }

            foreach($brandTotals as $brand_id => $carts_total){
                $brand_offer = $brand_offers->where('brand_id', $brand_id)->first();

                if ((int)$brand_offer->base_product->quantity <= $carts_total) {
                    $this->process_brand_offer($brand_offer, $order_detail, $order);
                }
            }
        }
        $offered_carts = CartOffered::where('user_id', $user->id)->where('is_postponed', 0)->get();
        foreach ($offered_carts as $cartItem) {
            $product = Product::find($cartItem->product_id);
            $product_stocks = ProductStock::where('product_id', $product->id);

            // Create a new instance of the OrderDetail model
            $offer_order_details = new OrderDetail();

            // Assign the properties from $order_detail to $offer_order_details
            $offer_order_details = $order_detail->replicate();

            $offer_order_details->order_id = $order->id;
            $offer_order_details->seller_id = $product->user_id;
            $offer_order_details->product_serial = $product->serial;
            $offer_order_details->product_matId = $product->mat_id;
            $offer_order_details->product_name = $product->ar_name;
            $offer_order_details->product_id = $product->id;
            $offer_order_details->variation = $product_variation;
            $offer_order_details->variation_qty = $cartItem->variation_qty;
            $offer_order_details->price =  $cartItem->price * $cartItem->quantity;
            $offer_order_details->original_price =  $cartItem->price * $cartItem->quantity;
            $offer_order_details->product_serial = $product->serial;
            $offer_order_details->tax = $product->tax;
            $offer_order_details->item_notes = $cartItem->item_notes;

            $offer_order_details->quantity = $cartItem->quantity;
            $offer_order_details->save();
            if($user->is_rep){
                $user->offers()->updateExistingPivot($cartItem->offer_id, ['user_id' => $order->for_customer, 'created_at' => now(), 'updated_at' => now()]);
            }
            $subtotal += $cartItem->price;

            // Delete the CartOffered record
            $cartItem->delete();
        }

        // if there is a postponed offers then make them active for the next order
        $offered_carts = CartOffered::where('user_id', $user->id)->where('is_postponed', 1)->get();
        foreach ($offered_carts as $offered_cart) {
            $offered_cart->update(['is_postponed' => 0]);
        }
        return $subtotal;
    }
}
