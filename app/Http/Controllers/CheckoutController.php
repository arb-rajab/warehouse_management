<?php

namespace App\Http\Controllers;

use App\Services\PaymentOptions;
use App\Services\CouponService;
use App\Services\GatewayPaymentService;
use App\Services\VerifiedPayment;
use App\Models\Address;
use App\Models\Carrier;
use App\Models\Cart;
use App\Models\CartOffered;
use App\Models\Category;
use App\Models\CombinedOrder;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreUser;
use App\Models\Visit;
use App\Utility\NotificationUtility;
use App\Utility\PayfastUtility;
use App\Utility\PayhereUtility;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Session;

class CheckoutController extends Controller
{

    public function __construct()
    {
        //
    }

    //check the selected payment gateway and redirect to that controller accordingly
    public function checkout(Request $request)
    {
        // Minumum order amount check
        if (get_setting('minimum_order_amount_check') == 1) {
            $subtotal = 0;
            foreach (Cart::where('user_id', Auth::user()->id)->get() as $key => $cartItem) {
                $product = Product::find($cartItem['product_id']);
                $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
            }
            if ($subtotal < get_setting('minimum_order_amount')) {
                $default_specific_product = Product::where('mat_id', get_setting('default_specific_product'))->first();
                $delivery_cart = Cart::where('user_id', auth()->user()->id)->where('product_id', $default_specific_product->id)->first();

                if (!$delivery_cart) {
                    $user_first_cart = Cart::where('user_id', Auth::user()->id)->first();

                    $newCartData = $user_first_cart->toArray();
                    $newCartData['quantity'] = 1;
                    $newCartData['variation_qty'] = null;
                    $newCartData['price'] = $default_specific_product->unit_price;
                    $newCartData['product_id'] = $default_specific_product->id;
                    Cart::create($newCartData);
                }

                // flash(translate('You order amount is less then the minimum order amount'))->warning();
                // return redirect()->route('home');
            }
        }
        // Minumum order amount check end

        if ($request->payment_option != null) {
            if (! app(PaymentOptions::class)->isAllowed((string) $request->payment_option)) {
                flash(translate('Select Payment Option.'))->warning();
                return back();
            }

            // A manual payment (no gateway controller) carries its own proof, checked
            // before the order exists so a bad submission leaves no order behind.
            $decorator = __NAMESPACE__ . '\\Payment\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $request->payment_option))) . "Controller";
            if (! class_exists($decorator)) {
                $request->validate(app(PaymentOptions::class)->proofRules());
            }

            // A refused order (empty cart, out of stock, a coupon that cannot be used)
            // comes back as a redirect, with nothing placed.
            $refusal = (new OrderController)->store($request);
            if ($refusal instanceof RedirectResponse) {
                return $refusal;
            }

            $request->session()->put('payment_type', 'cart_payment');

            $data['combined_order_id'] = $request->session()->get('combined_order_id');
            $request->session()->put('payment_data', $data);

            if ($request->session()->get('combined_order_id') != null) {

                // If block for Online payment, wallet and cash on delivery. Else block for Offline payment
                if (class_exists($decorator)) {
                    return (new $decorator)->pay($request);
                } else {
                    $combined_order = CombinedOrder::findOrFail($request->session()->get('combined_order_id'));
                    $manual_payment_data = [
                        'name'   => $request->payment_option,
                        'amount' => $combined_order->grand_total,
                        'trx_id' => $request->trx_id,
                        'photo'  => $request->photo,
                    ];
                    foreach ($combined_order->orders as $order) {
                        $order->manual_payment = 1;
                        $order->manual_payment_data = json_encode($manual_payment_data);
                        $order->save();
                    }
                    flash(translate('Your order has been placed successfully. Please submit payment information from purchase history'))->success();
                    return redirect()->route('order_confirmed');
                }
            }
        } else {
            flash(translate('Select Payment Option.'))->warning();
            return back();
        }
    }

    /**
     * Where a gateway's callback finishes a cart payment, once its server has
     * confirmed it. The signed-in customer must own the order and the amount must
     * pay its total (GatewayPaymentService); a forged, short or repeated callback
     * leaves the order as it was.
     */
    public function checkout_done(int $combined_order_id, VerifiedPayment $payment): RedirectResponse
    {
        $payerId = Auth::id();

        if ($payerId === null || ! app(GatewayPaymentService::class)->complete($payment, (int) $payerId, GatewayPaymentService::CART, $combined_order_id)) {
            flash(translate('Payment could not be verified'))->error();
            return redirect()->route('cart');
        }

        Session::put('combined_order_id', $combined_order_id);
        return redirect()->route('order_confirmed');
    }

    public function get_shipping_info(Request $request)
    {
        $carts = Cart::where('user_id', Auth::user()->id)->get();

        if (Auth::user()->is_rep) {
            $filteredCarts = $carts->where('for_customer', null);
            if ($filteredCarts->count() > 0) {
                flash(translate('Please pick a user.'))->warning();
                return redirect()->route('checkout.getCustomers');
            }

            $current_customer = Auth::user()->current_customer;

            if ($current_customer) {
                $customer_addresses = $current_customer->addresses;
            } else {
                $customer_addresses = Auth::user()->addresses;
            }
        } else {
            $customer_addresses = Auth::user()->addresses;
        }

        //        if (Session::has('cart') && count(Session::get('cart')) > 0) {
        if ($carts && count($carts) > 0) {
            $categories = Category::all();
            return view('shop.frontend.shipping_info', compact('categories', 'carts', 'customer_addresses'));
        }
        flash(translate('Your cart is empty'))->success();
        return back();
    }

    public function store_shipping_info(Request $request)
    {
        if ($request->address_id == null) {
            flash(translate("Please add shipping address"))->warning();
            return back();
        }

        $carts = Cart::where('user_id', Auth::user()->id)->get();
        if ($carts->isEmpty()) {
            flash(translate('Your cart is empty'))->warning();
            return redirect()->route('home');
        }

        Gate::authorize('manage', Address::findOrFail($request->address_id));

        foreach ($carts as $key => $cartItem) {
            $cartItem->address_id = $request->address_id;
            $cartItem->save();
        }

        $carrier_list = [];
        // if(get_setting('shipping_type') == 'carrier_wise_shipping'){
        //     $zone = Country::where('id',$carts[0]['address']['country_id'])->first()->zone_id;

        //     $carrier_query = Carrier::query();
        //     $carrier_query->whereIn('id',function ($query) use ($zone) {
        //         $query->select('carrier_id')->from('carrier_range_prices')
        //         ->where('zone_id', $zone);
        //     })->orWhere('free_shipping', 1);
        //     $carrier_list = $carrier_query->get();
        // }

        return view('shop.frontend.delivery_info', compact('carts', 'carrier_list'));
    }

    public function store_delivery_info(Request $request)
    { // not anymore
        $carts = Cart::with('product')->where('user_id', Auth::user()->id)->get();

        if ($carts->isEmpty()) {
            flash(translate('Your cart is empty'))->warning();
            return redirect()->route('home');
        }

        $addressIds = $carts->pluck('address_id')->unique();
        $shipping_info = Address::whereIn('id', $addressIds)->first();

        $carts->each(function ($cartItem) {
            //  $product = $cartItem->product;
            $cartItem->update([
                'shipping_cost' => 0,
                'shipping_type' => 'home_delivery',
            ]);
            $cartItem->save();
        });

        $subtotal = $carts->sum(function ($cartItem) {
            $product = $cartItem->product;
            return cart_product_price($cartItem, $product, false, false) * $cartItem->quantity;
        });

        $total = $subtotal;

        return view('shop.frontend.payment_select', compact('carts', 'shipping_info', 'total'));
    }

    public function apply_coupon_code(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:191']]);

        $coupon = Coupon::where('code', $request->code)->first();
        $response_message = [];

        if (! Cart::where('user_id', Auth::user()->id)->exists()) {
            return response()->json([
                'response_message' => ['response' => 'warning', 'message' => translate('Your cart is empty')],
                'html' => '',
            ]);
        }

        if ($coupon != null) {
            if (strtotime(date('d-m-Y')) >= $coupon->start_date && strtotime(date('d-m-Y')) <= $coupon->end_date) {
                if (CouponUsage::where('user_id', Auth::user()->id)->where('coupon_id', $coupon->id)->first() == null) {
                    $coupon_details = json_decode($coupon->details);

                    $carts = Cart::where('user_id', Auth::user()->id)
                        ->where('owner_id', $coupon->user_id)
                        ->get();

                    $coupon_discount = app(CouponService::class)->discountFor($coupon, $carts);

                    if ($carts->isNotEmpty() && $coupon_discount > 0) {
                        Cart::where('user_id', Auth::user()->id)
                            ->where('owner_id', $coupon->user_id)
                            ->update(
                                [
                                    'discount' => $coupon_discount / count($carts),
                                    'coupon_code' => $request->code,
                                    'coupon_applied' => 1,
                                ]
                            );
                        $response_message['response'] = 'success';
                        $response_message['message'] = translate('Coupon has been applied');
                    } else {
                        $response_message['response'] = 'warning';
                        $response_message['message'] = translate('This coupon is not applicable to your cart products!');
                    }
                } else {
                    $response_message['response'] = 'warning';
                    $response_message['message'] = translate('You already used this coupon!');
                }
            } else {
                $response_message['response'] = 'warning';
                $response_message['message'] = translate('Coupon expired!');
            }
        } else {
            $response_message['response'] = 'danger';
            $response_message['message'] = translate('Invalid coupon!');
        }

        $carts = Cart::where('user_id', Auth::user()->id)
            ->get();
        $shipping_info = Address::where('id', $carts->first()?->address_id)->first();

        $returnHTML = view('shop.frontend.partials.cart_summary', compact('coupon', 'carts', 'shipping_info'))->render();
        return response()->json(['response_message' => $response_message, 'html' => $returnHTML]);
    }

    public function remove_coupon_code(Request $request)
    {
        Cart::where('user_id', Auth::user()->id)
            ->update(
                [
                    'discount' => 0.00,
                    'coupon_code' => '',
                    'coupon_applied' => 0,
                ]
            );

        $coupon = Coupon::where('code', $request->code)->first();
        $carts = Cart::where('user_id', Auth::user()->id)
            ->get();

        $shipping_info = Address::where('id', $carts->first()?->address_id)->first();

        return view('shop.frontend.partials.cart_summary', compact('coupon', 'carts', 'shipping_info'));
    }

    public function apply_club_point(Request $request)
    {
        if (addon_is_activated('club_point')) {

            $point = $request->validate(['point' => ['required', 'integer', 'min:1']])['point'];

            if (Auth::user()->point_balance >= $point) {
                $request->session()->put('club_point', $point);
                flash(translate('Point has been redeemed'))->success();
            } else {
                flash(translate('Invalid point!'))->warning();
            }
        }
        return back();
    }

    public function remove_club_point(Request $request)
    {
        $request->session()->forget('club_point');
        return back();
    }

    public function order_confirmed()
    {
        $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));

        Cart::where('user_id', $combined_order->user_id)
            ->delete();

        //Session::forget('club_point');
        //Session::forget('combined_order_id');

        // foreach($combined_order->orders as $order){
        //     NotificationUtility::sendOrderPlacedNotification($order);
        // }

        return view('shop.frontend.order_confirmed', compact('combined_order'));
    }

    public function get_customers_to_choose()
    {
        $users = StoreUser::where('id', '!=', Auth::user()->id)->where('is_rep', false)->where('user_type', 'customer')->get();
        $carts = Cart::where('user_id', Auth::user()->id)->get();

        if (Auth::user()->is_rep) {
            return view('shop.frontend.view_customer', compact('users', 'carts'));
        }
    }

    public function choose_customer(Request $request)
    {
        $user = Auth::user();
        if (!$user->is_rep) {
            return response()->json([
                'error' => 'You cannot do this action, you are not representative',
                'result' => 0
            ], 403);
        }

        $request->validate([
            'id' => ['required', 'integer'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // The same accounts get_customers_to_choose() offers: never the rep,
        // another representative, or a seller or admin.
        $customer = StoreUser::where('id', '!=', $user->id)
            ->where('is_rep', false)
            ->where('user_type', 'customer')
            ->findOrFail($request->id);

        $open_visit = [];
        $open_visit['has_open_visit'] = false;
        $open_visit['visit_id'] = null;


        if (auth()->user()->rep_last_visit() && auth()->user()->rep_last_visit()->order_id == null) {
            if (auth()->user()->rep_last_visit()->customer_id != $customer->id) {
                $open_visit['has_open_visit'] = true;
                $open_visit['visit_id'] = auth()->user()->rep_last_open_visit()->id;

                auth()->user()->rep_last_visit()->note = 'Representative did not add a note for changing the customer';
                auth()->user()->rep_last_visit()->save();

                Visit::create([
                    'rep_id' => $user->id,
                    'customer_id' => $customer->id,
                    'rank' => auth()->user()->rep_last_visit_of_the_day()?->rank + 1 ?? 0,
                    'map_lat' => $request->latitude,
                    'map_lng' => $request->longitude,
                    'visit_date' => now(),
                ]);
            }
        } else {
            if (auth()->user()->rep_last_visit_of_the_day()) {
                $rank = auth()->user()->rep_last_visit_of_the_day()?->rank + 1;
            } else {
                $rank = 0;
            }
            Visit::create([
                'rep_id' => $user->id,
                'customer_id' => $customer->id,
                'rank' => $rank,
                'map_lat' => $request->latitude,
                'map_lng' => $request->longitude,
                'visit_date' => now(),
            ]);
        }
        // if($user->carts && $user->carts->count() > 0){
        //     $user->carts->each(function($cart) use ($customer){
        //         $cart->for_customer = $customer->id;
        //         $cart->save();
        //     });
        // }

        if ($user->carts && $user->carts->count() > 0) {
            $user->carts->each(function ($cart) use ($customer) {
                if ($cart->for_customer != $customer->id) {
                    $cart->delete();
                }
            });
        }
        if ($user->carts_offered && $user->carts_offered->count() > 0) {
            $user->carts_offered->each(function ($cart_offered) use ($customer) {
                if ($cart_offered->for_customer != $customer->id) {
                    $cart_offered->delete();
                }
            });
        }

        return response()->json([
            'Success' => 'Success',
            'result' => null,
            'message' => $message ?? null,
            'open_visit' => $open_visit
        ], 200);
    }
}
