<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\Cart;
use App\Models\CartOffered;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Shop;
use App\Models\User;
use App\Services\CartService;
use App\Services\OfferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Auth;
use Illuminate\Support\Facades\Validator;
class CartController extends Controller
{
    protected $offerService;

    protected function offerService()
    {
        return app(OfferService::class);
    }
    protected function cartService()
    {
        return app(CartService::class);
    }

    public function summary()
    {
        set_time_limit(0);

        //$user = User::where('id', auth()->user()->id)->first();
        $items = auth()->user()->carts;
        if ($items->isEmpty()) {
            return response()->json([
                'sub_total' => format_price(0.00),
                'tax' => format_price(0.00),
                'shipping_cost' => format_price(0.00),
                'discount' => format_price(0.00),
                'grand_total' => format_price(0.00),
                'grand_total_value' => 0.00,
                'coupon_code' => "",
                'coupon_applied' => false,
                'delivery_cost' => 0,
            ]);
        }

        $sum = 0.00;
        $subtotal = 0.00;
        $tax = 0.00;
        foreach ($items as $cartItem) {
            $item_sum = 0.00;

            $price = $cartItem->rep_price ? $cartItem->rep_price : $cartItem->price;

            // $item_sum += ($cartItem->price + $cartItem->tax) * $cartItem->quantity;
            $item_sum += $price * $cartItem->quantity;
            $item_sum += $cartItem->shipping_cost - $cartItem->discount;
            $sum +=  $item_sum;   //// 'grand_total' => $request->g

            $subtotal += $price * $cartItem->quantity;
            //$tax += $cartItem->tax * $cartItem->quantity;
        }

        $delivery_cost = 0;

        if ($subtotal < get_setting('minimum_order_amount')) {
            $default_specific_product = Product::where('mat_id', get_setting('default_specific_product'))->first();
            $delivery_cost = $default_specific_product->unit_price;
        }

        return response()->json([
            'sub_total' => format_price($subtotal),
            'tax' => format_price($tax),
            'shipping_cost' => format_price($items->sum('shipping_cost')),
            'discount' => format_price($items->sum('discount')),
            'grand_total' => format_price($sum),
            'grand_total_value' => convert_price($sum),
            'coupon_code' => $items[0]->coupon_code,
            'coupon_applied' => $items[0]->coupon_applied == 1,
            'delivery_cost' => format_price($delivery_cost),
        ]);
    }


    public function count()
    {
        set_time_limit(0);

        $items = auth()->user()->carts;
        $offered_items = auth()->user()->carts_offered;
        $count = sizeof($items) + sizeof($offered_items);
        return response()->json([
            'count' => $count,
            'status' => true,
        ]);
    }



    public function getList(Request $request)
    {
        set_time_limit(0);

        $owner_ids = Cart::where('user_id', $request->user()->id)->select('owner_id')->groupBy('owner_id')->pluck('owner_id')->toArray();
        $currency_symbol = currency_symbol();
        $shops = [];
        if (!empty($owner_ids)) {
            foreach ($owner_ids as $owner_id) {
                $shop = array();
                $shop_items_raw_data = Cart::where('user_id', $request->user()->id)->where('owner_id', $owner_id)->get()->toArray();
                $shop_items_data = array();
                if (!empty($shop_items_raw_data)) {
                    foreach ($shop_items_raw_data as $shop_items_raw_data_item) {
                        $decodedData = json_decode($shop_items_raw_data_item["variation_qty"], true);
                        // convert to array of objects
                        $transformedData = [];
                        if ($decodedData) {
                            foreach ($decodedData as $name => $quantity) {
                                $transformedData[] = [
                                    'name' => $name,
                                    'quantity' => $quantity, // Assuming you want to divide the quantity by 2, adjust as needed
                                ];
                            }
                        }

                        $product = Product::where('id', $shop_items_raw_data_item["product_id"])->first();
                        $product_stocks = ProductStock::where('product_id', $product->id);
                        $shop_items_data_item["id"] = intval($shop_items_raw_data_item["id"]);
                        $shop_items_data_item["owner_id"] = intval($shop_items_raw_data_item["owner_id"]);
                        $shop_items_data_item["user_id"] = intval($shop_items_raw_data_item["user_id"]);
                        $shop_items_data_item["product_id"] = intval($shop_items_raw_data_item["product_id"]);
                        $shop_items_data_item["product_name"] = $product->getTranslation('name');
                        $shop_items_data_item["product_thumbnail_image"] = uploaded_asset($product->thumbnail_img);
                        $shop_items_data_item["variation"] = $shop_items_raw_data_item["variation"];
                        $shop_items_data_item["price"] = (float) cart_product_price($shop_items_raw_data_item, $product, false, false);
                        $shop_items_data_item["currency_symbol"] = $currency_symbol;
                        $shop_items_data_item["tax"] = (float) cart_product_tax($shop_items_raw_data_item, $product, false);
                        $shop_items_data_item["shipping_cost"] = (float) $shop_items_raw_data_item["shipping_cost"];
                        $shop_items_data_item["quantity"] = intval($shop_items_raw_data_item["quantity"]);
                        $shop_items_data_item["variation_qty"] = $transformedData;
                        $shop_items_data_item["tax"] = $product->tax;
                        $shop_items_data_item["mat_id"] = $product->mat_id;
                        $shop_items_data_item["item_notes"] = $shop_items_raw_data_item["item_notes"];
                        $shop_items_data_item["rep_price"] = (float) $shop_items_raw_data_item["rep_price"];
                        $shop_items_data_item["is_price_changed"] = $shop_items_raw_data_item["is_price_changed"];


                        if ($request->user()->is_rep) {
                            $shop_items_data_item["by_rep"] = $shop_items_raw_data_item["by_rep"];
                            $shop_items_data_item["for_customer"] = $shop_items_raw_data_item["for_customer"];
                        }

                        $shop_items_data_item["lower_limit"] = intval($product->min_qty);
                        $shop_items_data_item["upper_limit"] = intval($product_stocks->where('variant', $shop_items_raw_data_item['variation'])->first()?->qty);

                        $shop_items_data[] = $shop_items_data_item;
                    }
                }

                $carts_offered = CartOffered::where('user_id', $request->user()->id)->get();

                if ($carts_offered->count() > 0) {

                    foreach ($carts_offered as $cart_offered) {
                        $product = Product::where('id', $cart_offered->product_id)->first();

                        $cart_offered->product_name = $product->getTranslation('name');
                        $cart_offered->product_thumbnail_image = uploaded_asset($product->thumbnail_img);

                        $decodedData = json_decode($cart_offered->variation_qty, true);
                        // convert to array of objects
                        $transformedData = [];
                        if ($decodedData) {
                            foreach ($decodedData as $name => $quantity) {
                                $transformedData[] = [
                                    'name' => $name,
                                    'quantity' => $quantity, // Assuming you want to divide the quantity by 2, adjust as needed
                                ];
                            }
                        }
                        $cart_offered->variation_qty = $transformedData ?? null;
                        $cart_offered->mat_id = $product->mat_id;
                        $cart_offered->rep_price = null;
                        $cart_offered->is_price_changed = null;
                        $cart_offered->offer_cart = true;
                        $cart_offered->tax = 9;
                        $cart_offered->shipping_cost = 0;
                        $cart_offered->currency_symbol = "€";
                        $shop_items_data[] = $cart_offered;
                    }
                }

                $shop_data = Shop::where('user_id', $owner_id)->first();
                if ($shop_data) {
                    $shop['name'] = $shop_data->name;
                    $shop['owner_id'] = (int) $owner_id;
                    $shop['cart_items'] = $shop_items_data;
                } else {
                    $shop['name'] = "Inhouse";
                    $shop['owner_id'] = (int) $owner_id;
                    $shop['cart_items'] = $shop_items_data;
                }
                $shops[] = $shop;
            }
        }

        //dd($shops);

        return response()->json($shops);
    }


    public function add(Request $request)
    {
        set_time_limit(0);

        if (Auth::check() && Auth::user()->is_rep) {
            if (!auth()->user()->has_open_visit()) {
                return response()->json([
                    'result' => false,
                    'message' => "Please make a visit first"
                ], 403);
            }
        }


        $product = Product::with('stocks')->find($request->id);
        if (!$product) {
            return response()->json(['result' => false, 'message' => "Product not found"], 404);
        }
        $authUser = $request->user();
        $product_stocks = ProductStock::where('product_id', $product->id);
        $is_rep = $authUser->is_rep ?? false;
        $carts = Cart::where("user_id", auth()->id())->get();
        foreach ($carts as $cart) {
            if (isset($cart->for_customer)) {
                $for_customer = $cart->for_customer;
            }
        }
        $variant = $request->variant;
        $tax = 0;

        if ($variant == '')
            $price = $product->unit_price;
        else {

            // dd ($product_stocks->where('variant', 'like', '%p%')->first());
            $product_stock = $product_stocks->where('variant', 'like', '%' . $variant . '%')->first();
            $price = $product_stock->price;
        }


        if (isset($request->variation_qty)) {

            $variations = $request->variation_qty;

            // Initialize an array to store valid variations
            $validVariations = [];
            // Iterate through variations and filter out those with value 0
            foreach ($variations as $name => $value) {
                if ($value > 0) {
                    // Add the valid variation to the array
                    $validVariations[$name] = $value;
                }
            }
            $variation_qty = json_encode($validVariations);
        }


        if (isset($request->item_notes)) {
            $item_notes = $request->item_notes;
        }



        if (isset($request->rep_price)) {
            $discounted_price = $request->rep_price; // the price which the rep entered
            $original_price = $price;
            $representative_discount_percentage  = Auth::user()->rep_discount_percentage;
            if (! $representative_discount_percentage) { // if the rep does not have a package
                return response()->json(['result' => false, 'message' => translate('Only Representatives with discount packages can change the price')], 403);
            }
            $representative_discount_percentage  = $representative_discount_percentage  / 100;
            $minimum_discount_price = $original_price - ($original_price * $representative_discount_percentage); // the minimum limit
            if ($discounted_price >= $minimum_discount_price) {
                if ($discounted_price <= $original_price) {
                    $rep_price = $discounted_price;
                    $is_price_changed = true;
                } else {
                    return response()->json(['result' => false, 'message' => translate('Price can not be higher than original price!')], 403);
                }
            } else {
                return response()->json(['result' => false, 'message' => translate('Price exceeds your discount limit!')], 403);
            }
        }
        //discount calculation based on flash deal and regular discount
        //calculation of taxes
        $discount_applicable = false;

        if ($product->discount_start_date == null) {
            $discount_applicable = true;
        } elseif (
            strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
            strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date
        ) {
            $discount_applicable = true;
        }

        if ($discount_applicable) {
            if ($product->discount_type == 'percent') {
                $price -= ($price * $product->discount) / 100;
            } elseif ($product->discount_type == 'amount') {
                $price -= $product->discount;
            }
        }

        foreach ($product->taxes as $product_tax) {
            if ($product_tax->tax_type == 'percent') {
                $tax += ($price * $product_tax->tax) / 100;
            } elseif ($product_tax->tax_type == 'amount') {
                $tax += $product_tax->tax;
            }
        }

        if ($product->min_qty > $request->quantity) {
            return response()->json(['result' => false, 'message' => translate("Minimum") . " {$product->min_qty} " . translate("item(s) should be ordered")], 200);
        }

        if ($product->max_qty < $request->quantity) {
            return response()->json(['result' => false, 'message' => translate("Maximum") . " {$product->max_qty} " . translate("item(s) should be ordered")], 200);
        }



        $stock = $product_stocks->where('variant', 'like', '%' . $variant . '%')->first()->qty;

        $variant_string = $variant != null && $variant != "" ? translate("for") . " ($variant)" : "";
        if ($stock < $request->quantity && $product->digital == 0) {
            if ($stock == 0) {
                return response()->json(['result' => false, 'message' => "Stock out"], 200);
            } else {
                return response()->json(['result' => false, 'message' => translate("Only") . " {$stock} " . translate("item(s) are available") . " {$variant_string}"], 200);
            }
        }

        $cart_item = Cart::where('product_id', $request->id)->where("user_id", auth()->id())->where('variation', $variant)->first();
        if ($cart_item && $cart_item->product->digital == 1) {
            return response()->json(['result' => false, 'message' => 'Already added this product']);
        }

        if ($cart_item) {
            $variationQty = json_decode($cart_item->variation_qty, true); //old variation
            if (isset($variation_qty)) {
                $newVariation =  json_decode($variation_qty, true); // new variation
                // Merge old and new variations
                if (is_array($newVariation)) {
                    if (is_null($variationQty)) {
                        $variationQty = [];
                    }
                    $variationQty = array_merge($variationQty, $newVariation);
                }
            }

            if (isset($request->item_notes)) { // update
                $cart_item->item_notes = $request->item_notes;
            }


            if (isset($request->rep_price)) {
                $discounted_price = $request->rep_price; // the price which the rep entered
                $original_price = $price;
                $representative_discount_percentage  = Auth::user()->rep_discount_percentage;
                if (! $representative_discount_percentage) { // if the rep does not have a package
                    return response()->json(['result' => false, 'message' => translate('Only Representatives with discount packages can change the price')], 403);
                }
                $representative_discount_percentage  = $representative_discount_percentage  / 100;
                $minimum_discount_price = $original_price - ($original_price * $representative_discount_percentage); // the minimum limit
                if ($discounted_price >= $minimum_discount_price) {
                    if ($discounted_price <= $original_price) {
                        $rep_price = $discounted_price;
                        $is_price_changed = true;
                    } else {
                        return response()->json(['result' => false, 'message' => translate('Price can not be higher than original price!')], 403);
                    }
                } else {
                    return response()->json(['result' => false, 'message' => translate('Price exceeds your discount limit!')], 403);
                }
            } //

            $cart_item->save();
            $product_quantity_in_cart = $cart_item->quantity;
        }




        $cart_data = Cart::updateOrCreate([
            'user_id' => $authUser->id,
            'owner_id' => $product->user_id,
            'product_id' => $request->id,
            'variation' => $variant,
        ], [
            'price' => $price,
            'rep_price' => $rep_price ?? 0,
            'is_price_changed' => $is_price_changed ?? false,
            'item_notes' => isset($item_notes) ? $item_notes : (isset($cart_item->item_notes) ? $cart_item->item_notes : null),
            'tax' => $tax, // User Experience
            'shipping_cost' => 0,
            'quantity' => $request->quantity,
            'for_customer' => isset($for_customer) ? $for_customer : null,
            'by_rep' => $is_rep,
            'variation_qty' => isset($variationQty) ? json_encode($variationQty) : (isset($variation_qty) ? $variation_qty : null)
        ]);

        $offerMessage = $this->offerService()->processOffer($product, $cart_data, $product_quantity_in_cart ?? $request->quantity, $request);

        if (Auth::check() && Auth::user()->is_rep) {
            $this->cartService()->setVisitCustomerToCart();
        } elseif (Auth::user()->is_rep) {
            $this->cartService()->setVisitCustomerToCart();
        }

        if (\App\Utility\NagadUtility::create_balance_reference($request->cost_matrix) == false) {
            return response()->json(['result' => false, 'message' => 'Cost matrix error']);
        }

        return response()->json([
            'result' => true,
            'message' => translate('Product added to cart successfully'),
            'offer_message' => $offerMessage ?? null
        ]);
    }

    public function changeQuantity(Request $request)
    {
        $cart = Cart::find($request->id);
        if ($cart != null) {

            if ($cart->product->stocks->where('variant', $cart->variation)->first()->qty >= $request->quantity) {
                $cart->update([
                    'quantity' => $request->quantity
                ]);

                return response()->json(['result' => true, 'message' => translate('Cart updated')], 200);
            } else {
                return response()->json(['result' => false, 'message' => translate('Maximum available quantity reached')], 200);
            }
        }

        return response()->json(['result' => false, 'message' => translate('Something went wrong')], 200);
    }

    public function process(Request $request)
    {
        $cart_ids = explode(",", $request->cart_ids);
        $cart_quantities = explode(",", $request->cart_quantities);

        if (!empty($cart_ids)) {
            $i = 0;
            foreach ($cart_ids as $cart_id) {
                $cart_item = Cart::where('id', $cart_id)->first();
                if ($cart_item) {
                    $product = Product::where('id', $cart_item->product_id)->first();

                    if ($product->min_qty > $cart_quantities[$i]) {
                        return response()->json(['result' => false, 'message' => translate("Minimum") . " {$product->min_qty} " . translate("item(s) should be ordered for") . " {$product->name}"], 200);
                    }

                    if ($product->max_qty < $cart_quantities[$i]) {
                        return response()->json(['result' => false, 'message' => translate("Maximum") . " {$product->max_qty} " . translate("item(s) should be ordered for") . " {$product->name}"], 200);
                    }

                    $stock = $cart_item->product->stocks->where('variant', $cart_item->variation)->first()->qty;
                    $variant_string = $cart_item->variation != null && $cart_item->variation != "" ? " ($cart_item->variation)" : "";
                    if ($stock >= $cart_quantities[$i] || $product->digital == 1) {
                        $cart_item->update([
                            'quantity' => $cart_quantities[$i]
                        ]);
                    } else {
                        if ($stock == 0) {
                            return response()->json(['result' => false, 'message' => translate("No item is available for") . " {$product->name}{$variant_string}," . translate("remove this from cart")], 200);
                        } else {
                            return response()->json(['result' => false, 'message' => translate("Only") . " {$stock} " . translate("item(s) are available for") . " {$product->name}{$variant_string}"], 200);
                        }
                    }
                }

                $i++;
            }

            return response()->json(['result' => true, 'message' => translate('Cart updated')], 200);
        } else {
            return response()->json(['result' => false, 'message' => translate('Cart is empty')], 200);
        }
    }

    public function destroy($id)
    {
        $this->offerService()->removeProductOfferFromCart($id);

        Cart::destroy($id);

        return response()->json(['result' => true, 'message' => translate('Product is successfully removed from your cart')], 200);
    }
    public function update_item_price(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:carts,id',
            'rep_price' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['result' => false, 'message' => $validator->errors()->first()], 422);
        }

        $cart = Cart::where('user_id', Auth::user()->id)->where('id', $request->id)->first();

        $discounted_price = $request->rep_price; // the price which the rep entered
        $original_price = $cart->price;
        $representative_discount_percentage  = Auth::user()->rep_discount_percentage;
        if (! $representative_discount_percentage) { // if the rep does not have a package
            return response()->json(['result' => false, 'message' => translate('Only Representatives with discount packages can change the price')], 403);
        }
        $representative_discount_percentage  = $representative_discount_percentage  / 100;
        $minimum_discount_price = $original_price - ($original_price * $representative_discount_percentage); // the minimum limit
        if ($discounted_price >= $minimum_discount_price) {
            if ($discounted_price <= $original_price) {
                $cart->rep_price = $discounted_price;
                $cart->is_price_changed = true;
                if ($cart->save()) {
                    return response()->json(['result' => true, 'message' => translate('Price Updated Successfully')], 200);
                } else {
                    return response()->json(['result' => false, 'message' => translate('Error!')], 200);
                }
            } else {
                return response()->json(['result' => false, 'message' => translate('Price can not be higher than original price!')], 403);
            }
        } else {
            return response()->json(['result' => false, 'message' => translate('Price exceeds your discount limit!')], 403);
        }
    }
}
