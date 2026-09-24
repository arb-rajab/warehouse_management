<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Category;
use App\Models\Cart;
use App\Models\CartOffered;
use App\Models\ProductStock;
use App\Services\CartService;
use App\Services\OfferService;
use Auth;
use Session;
use Cookie;

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

    public function index(Request $request)
    {
        if (auth()->user() != null) {
            $user_id = Auth::user()->id;
            if ($request->session()->get('temp_user_id')) {
                Cart::where('temp_user_id', $request->session()->get('temp_user_id'))
                    ->update(
                        [
                            'user_id' => $user_id,
                            'temp_user_id' => null
                        ]
                    );

                Session::forget('temp_user_id');
            }
            $carts = Cart::where('user_id', $user_id)->get();
        } else {
            $temp_user_id = $request->session()->get('temp_user_id');
            $carts = ($temp_user_id != null) ? Cart::where('temp_user_id', $temp_user_id)->get() : [];
        }

        $cartResults = [];

        foreach ($carts as $cart) {
            if (isset($cart->variation_qty) && $cart->variation_qty != 'null') {
                $jsonData = $cart->variation_qty;

                $variationData = json_decode($jsonData, true);
                $results = [];
                $cartResults[$cart->id] = $variationData;
            }
        }
        // return $cartResults;
        return view('frontend.view_cart', compact('carts', 'cartResults'));
    }

    public function showCartModal(Request $request)
    {
        $product = Product::find($request->id);
        $variations = ProductVariation::where('product_id', $product->id)->get();

        return view('frontend.partials.addToCart', compact('product', 'variations'));
    }

    public function showCartModalAuction(Request $request)
    {
        $product = Product::find($request->id);
        return view('auction.frontend.addToCartAuction', compact('product'));
    }

    public function addToCart(Request $request)
    {
        if(Auth::check() && Auth::user()->is_rep){
            $result = $this->cartService()->checkRepVisit();
            if($result['status'] != 'success'){
                return array(
                    'status' => 0,
                    'modal_view' => view('frontend.partials.noVisit', compact('result'))->render(),
                    'nav_cart_view' => view('frontend.partials.cart')->render(),
                );
            }
        }

        // return $request;
        $product = Product::find($request->id);
        $product_stocks = ProductStock::where('product_id', $product->id);
        if ($product->max_qty < $request->quantity) {
            return response()->json([
                'error' => 'Failed to add to cart. The quantity is more than the max quantity you can add.'
            ], 400);
        }
        $carts = array();
        $data = array();

        if (auth()->user() != null) {
            $user_id = Auth::user()->id;
            $data['user_id'] = $user_id;
            $carts = Cart::where('user_id', $user_id)->get();
        } else {
            if ($request->session()->get('temp_user_id')) {
                $temp_user_id = $request->session()->get('temp_user_id');
            } else {
                $temp_user_id = bin2hex(random_bytes(10));
                $request->session()->put('temp_user_id', $temp_user_id);
            }
            $data['temp_user_id'] = $temp_user_id;
            $carts = Cart::where('temp_user_id', $temp_user_id)->get();
        }

        $data['product_id'] = $product->id;
        $data['owner_id'] = $product->user_id;

        $str = '';
        $tax = 0;
        if ($product->auction_product == 0) {
            if ($product->digital != 1 && $request->quantity < $product->min_qty) {
                return array(
                    'status' => 0,
                    'cart_count' => count($carts),
                    'modal_view' => view('frontend.partials.minQtyNotSatisfied', ['min_qty' => $product->min_qty])->render(),
                    'nav_cart_view' => view('frontend.partials.cart')->render(),
                );
            }

            //check the color enabled or disabled for the product
            if ($request->has('color')) {
                $str = $request['color'];
            }

            if ($product->digital != 1) {
                //Gets all the choice values of customer choice option and generate a string like Black-S-Cotton
                foreach (json_decode(Product::find($request->id)->choice_options) as $key => $choice) {
                    if ($str != null) {
                        $str .= '-' . str_replace(' ', '', $request['attribute_id_' . $choice->attribute_id]);
                    } else {
                        $str .= str_replace(' ', '', $request['attribute_id_' . $choice->attribute_id]);
                    }
                }
            }

            $data['variation'] = $str;


            if (isset($request->variation)) {

                $variations = $request->variation;

                // Initialize an array to store valid variations
                $validVariations = [];

                // Iterate through variations and filter out those with value 0
                foreach ($variations as $name => $value) {
                    if ($value > 0) {
                        // Add the valid variation to the array
                        $validVariations[$name] = $value;
                    }
                }

                $data['variation_qty'] = json_encode($validVariations, JSON_UNESCAPED_UNICODE);
            }



            $product_stock = $product_stocks->where('variant', 'like', '%' . $str . '%')->first();
            $price = $product_stock->price;

            if ($product->wholesale_product) {
                $wholesalePrice = $product_stock->wholesalePrices->where('min_qty', '<=', $request->quantity)->where('max_qty', '>=', $request->quantity)->first();
                if ($wholesalePrice) {
                    $price = $wholesalePrice->price;
                }
            }

            $quantity = $product_stock->qty;

            if ($quantity < $request['quantity']) {
                return array(
                    'status' => 0,
                    'cart_count' => count($carts),
                    'modal_view' => view('frontend.partials.outOfStockCart')->render(),
                    'nav_cart_view' => view('frontend.partials.cart')->render(),
                );
            }

            //discount calculation
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

            //calculation of taxes
            foreach ($product->taxes as $product_tax) {
                if ($product_tax->tax_type == 'percent') {
                    $tax += ($price * $product_tax->tax) / 100;
                } elseif ($product_tax->tax_type == 'amount') {
                    $tax += $product_tax->tax;
                }
            }
            foreach($carts as $cart){
                if(isset($cart->for_customer)){
                    $for_customer = $cart->for_customer;
                }
            }
            $data['quantity'] = $request['quantity'];
            $data['price'] = $price;
            $data['tax'] = $tax;
            //$data['shipping'] = 0;
            $data['shipping_cost'] = 0;
            $data['product_referral_code'] = null;
            $data['cash_on_delivery'] = $product->cash_on_delivery;
            $data['digital'] = $product->digital;
            $data['for_customer'] = isset($for_customer) ? $for_customer : null;
            if (Auth::check()) {
                $data['by_rep'] = Auth::user()->is_rep ? true : false;
            }
            if ($request['quantity'] == null) {
                $data['quantity'] = 1;
            }

            if (Cookie::has('referred_product_id') && Cookie::get('referred_product_id') == $product->id) {
                $data['product_referral_code'] = Cookie::get('product_referral_code');
            }

            if ($carts && count($carts) > 0) {
                $foundInCart = false;

                foreach ($carts as $key => $cartItem) {
                    $cart_product = Product::where('id', $cartItem['product_id'])->first();
                    $cart_product_stocks = ProductStock::where('product_id', $product->id);
                    if ($cart_product->auction_product == 1) {
                        return array(
                            'status' => 0,
                            'cart_count' => count($carts),
                            'modal_view' => view('frontend.partials.auctionProductAlredayAddedCart')->render(),
                            'nav_cart_view' => view('frontend.partials.cart')->render(),
                        );
                    }

                    if ($cartItem['product_id'] == $request->id) {
                        $product_stock = $cart_product_stocks->where('variant', 'like', '%' . $str . '%')->first();
                        $quantity = $product_stock->qty;
                        if ($quantity < $cartItem['quantity'] + $request['quantity']) {
                            return array(
                                'status' => 0,
                                'cart_count' => count($carts),
                                'modal_view' => view('frontend.partials.outOfStockCart')->render(),
                                'nav_cart_view' => view('frontend.partials.cart')->render(),
                            );
                        }
                        if (($str != null && $cartItem['variation'] == $str) || $str == null) {
                            $foundInCart = true;

                            $cartItem['quantity'] += $request['quantity'];

                            $variationQty = json_decode($cartItem['variation_qty'], true);
                            if(isset($data['variation_qty'])){
                                $newVariation = json_decode($data['variation_qty'], true);
                            }
                            // $newVariation = $request->variation;

                            if (isset($newVariation)) {
                                foreach ($newVariation as $variationId => $quantity) {
                                    if (isset($variationQty[$variationId])) {
                                        $variationQty[$variationId] += $quantity;
                                    } else {
                                        $variationQty[$variationId] = $quantity;
                                    }
                                }
                            }

                            $cartItem['variation_qty'] = json_encode($variationQty);


                            if ($cart_product->wholesale_product) {
                                $wholesalePrice = $product_stock->wholesalePrices->where('min_qty', '<=', $request->quantity)->where('max_qty', '>=', $request->quantity)->first();
                                if ($wholesalePrice) {
                                    $price = $wholesalePrice->price;
                                }
                            }

                            $cartItem['price'] = $price;

                            $cartItem->save();
                            $product_quantity_in_cart = $cartItem['quantity'];
                        }
                    }
                }
                if (!$foundInCart) {
                    Cart::create($data);
                }
            } else {
                Cart::create($data);
            }

            if (auth()->user() != null) {
                $user_id = Auth::user()->id;
                $carts = Cart::where('user_id', $user_id)->get();
            } else {
                $temp_user_id = $request->session()->get('temp_user_id');
                $carts = Cart::where('temp_user_id', $temp_user_id)->get();
            }

            $offer_message = $this->offerService()->processOffer($product, $data, $product_quantity_in_cart ?? $request->quantity, $request);

            if(Auth::check() && Auth::user()->is_rep){
                $result = $this->cartService()->setVisitCustomerToCart();
            }

            return array(
                'status' => 1,
                'cart_count' => count($carts),
                'modal_view' => view('frontend.partials.addedToCart', compact('product', 'data', 'offer_message'))->render(),
                'nav_cart_view' => view('frontend.partials.cart')->render(),
                'variation' => json_encode($request->variation)

            );
        } else {
            $price = $product->bids->max('amount');

            foreach ($product->taxes as $product_tax) {
                if ($product_tax->tax_type == 'percent') {
                    $tax += ($price * $product_tax->tax) / 100;
                } elseif ($product_tax->tax_type == 'amount') {
                    $tax += $product_tax->tax;
                }
            }

            $data['quantity'] = 1;
            $data['price'] = $price;
            $data['tax'] = $tax;
            $data['shipping_cost'] = 0;
            $data['product_referral_code'] = null;
            $data['cash_on_delivery'] = $product->cash_on_delivery;
            $data['digital'] = $product->digital;

            if (count($carts) == 0) {
                Cart::create($data);
            }
            if (auth()->user() != null) {
                $user_id = Auth::user()->id;
                $carts = Cart::where('user_id', $user_id)->get();
            } else {
                $temp_user_id = $request->session()->get('temp_user_id');
                $carts = Cart::where('temp_user_id', $temp_user_id)->get();
            }
            return array(
                'status' => 1,
                'cart_count' => count($carts),
                'modal_view' => view('frontend.partials.addedToCart', compact('product', 'data'))->render(),
                'nav_cart_view' => view('frontend.partials.cart')->render(),
            );
        }
    }

    //removes from Cart
    public function removeFromCart(Request $request)
    {
        $this->offerService()->removeProductOfferFromCart($request->id);

        Cart::destroy($request->id);
        if (auth()->user() != null) {
            $user_id = Auth::user()->id;
            $carts = Cart::where('user_id', $user_id)->get();
        } else {
            $temp_user_id = $request->session()->get('temp_user_id');
            $carts = Cart::where('temp_user_id', $temp_user_id)->get();
        }

        return redirect()->back()->with([
            'cart_count' => count($carts),
            'cart_view' => view('frontend.partials.cart_details', compact('carts'))->render(),
            'nav_cart_view' => view('frontend.partials.cart')->render(),
            'notification' => [
                'type' => 'success',
                'message' => 'Item has been removed from the cart.',
            ],
        ]);
    }

    //updated the quantity for a cart item
    public function updateQuantity(Request $request)
    {
        $cartItem = Cart::findOrFail($request->id);

        if ($cartItem['id'] == $request->id) {
            $product = Product::find($cartItem['product_id']);
            $product_stocks = ProductStock::where('product_id', $product->id);
            $product_stock = $product_stocks->where('variant', 'like', '%' . $cartItem['variation'] . '%')->first();
            $quantity = $product_stock->qty;
            $price = $product_stock->price;

            //discount calculation
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

            if ($quantity >= $request->quantity) {
                if ($request->quantity >= $product->min_qty) {
                    $cartItem['quantity'] = $request->quantity;
                }
            }

            if ($product->wholesale_product) {
                $wholesalePrice = $product_stock->wholesalePrices->where('min_qty', '<=', $request->quantity)->where('max_qty', '>=', $request->quantity)->first();
                if ($wholesalePrice) {
                    $price = $wholesalePrice->price;
                }
            }

            $cartItem['price'] = $price;
            $cartItem->save();
        }

        if (auth()->user() != null) {
            $user_id = Auth::user()->id;
            $carts = Cart::where('user_id', $user_id)->get();
        } else {
            $temp_user_id = $request->session()->get('temp_user_id');
            $carts = Cart::where('temp_user_id', $temp_user_id)->get();
        }

        return array(
            'cart_count' => count($carts),
            'cart_view' => view('frontend.partials.cart_details', compact('carts'))->render(),
            'nav_cart_view' => view('frontend.partials.cart')->render(),
        );
    }

    public function update_item_notes(Request $request)
    {
        $cart = Cart::find($request->id);
        if(!$cart){
            return 0;
        }

        $cart->item_notes = $request->item_notes;
        if ($cart->save()) {
            return 1;
        }
        return 0;
    }
    public function update_item_price(Request $request){
        $cart = Cart::find($request->id);
        if(!$cart){
            return 0;
        }

        $discounted_price = $request->rep_price; // the price which the rep entered
        $original_price = $cart->price;
        $representative_discount_percentage  = Auth::user()->rep_discount_percentage;
        $representative_discount_percentage  = $representative_discount_percentage  / 100;
        $minimum_discount_price = $original_price - ($original_price * $representative_discount_percentage); // the minimum limit
        if ($discounted_price >= $minimum_discount_price && $discounted_price <= $original_price){
            $cart->rep_price = $discounted_price;
            $cart->is_price_changed = true;
            if ($cart->save()) {
                return 1;
            }
        }
        return 0;
    }

}
