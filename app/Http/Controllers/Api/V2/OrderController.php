<?php

namespace App\Http\Controllers\Api\V2;

use \App\Utility\NotificationUtility;
use App\Http\Controllers\AffiliateController;
use App\Models\Address;
use App\Models\BusinessSetting;
use App\Models\Cart;
use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\StoreUser;
use App\Services\OfferService;
use App\Services\OrderPlacementService;
use App\Services\OrderService;
use App\Services\PaymentOptions;
use App\Services\WalletPaymentService;
use Auth;
use Carbon\Carbon;
use DB;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrderController extends Controller
{
    protected $offerService;

    protected function orderPlacementService(): OrderPlacementService
    {
        return app(OrderPlacementService::class);
    }

    protected function offerService()
    {
        return app(OfferService::class);
    }

    protected $orderService;

    protected function orderService()
    {
        return app(OrderService::class);
    }

    /**
     * Place the signed-in customer's cart as an order. With `$payFromWallet`
     * the wallet pays for it in the same transaction, and a balance that does
     * not cover it places no order and keeps the cart.
     */
    public function store(Request $request, bool $payFromWallet = false)
    {
        set_time_limit(0);

        if ($request->filled('payment_type') && ! app(PaymentOptions::class)->isAllowed((string) $request->payment_type)) {
            return $this->failed(__('storefront.checkout.select_payment_option'));
        }

        if (auth()->user()->is_rep) {
            $filteredCarts = Cart::where('user_id', auth()->user()->id)->where('for_customer', null)->get();
            if ($filteredCarts->count() > 0) {
                $forCustomerId = Cart::where('user_id', auth()->user()->id)->where('for_customer', '!=', null)->latest()->first()?->for_customer;

                if (!$forCustomerId) {
                    return $this->failed(__('api.order.customer_required'));
                }

                foreach ($filteredCarts as $cart) {
                    $cart->for_customer = $forCustomerId;
                    $cart->save();
                }
            }
        }

        $cartItems = Cart::where('user_id', auth()->user()->id)->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'combined_order_id' => 0,
                'result' => false,
                'message' => __('api.cart.empty')
            ]);
        }

        if (get_setting('minimum_order_amount_check') == 1) {
            $subtotal = 0;
            foreach (Cart::where('user_id', auth()->user()->id)->get() as $key => $cartItem) {
                $product = Product::find($cartItem['product_id']);
                $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
            }
            if ($subtotal < get_setting('minimum_order_amount')) {
                $default_specific_product = Product::where('mat_id', get_setting('default_specific_product'))->first();
                $delivery_cart = Cart::where('user_id', auth()->user()->id)->where('product_id', $default_specific_product->id)->first();;

                if (!$delivery_cart) {
                    $user_first_cart = Cart::where('user_id', Auth::user()->id)->first();

                    $newCartData = $user_first_cart->toArray();
                    $newCartData['quantity'] = 1;
                    $newCartData['variation_qty'] = null;
                    $newCartData['price'] = $default_specific_product->unit_price;
                    $newCartData['product_id'] = $default_specific_product->id;
                    Cart::create($newCartData);
                }

                // return $this->failed("You order amount is less then the minimum order amount");
            }
        }

        if (auth()->user()->is_rep) {
            $cartItems->loadMissing(['product.collection', 'rep_customer']);
            $profits = 0;
            $total_price = 0;
            foreach ($cartItems as $cartItem) {
                if ($cartItem["is_price_changed"] == true) {
                    $price = $cartItem['rep_price'] * $cartItem['quantity'];
                } else {
                    $price = cart_product_price($cartItem, $cartItem->product, false, false) * $cartItem['quantity'];
                }
                $total_price += $price;
                $profits += (float)($price * $cartItem->product->collection?->amount ?? 1) / 100;
            }
            $indicator = ($profits / ($total_price == 0 ? 1 : $total_price)) * 100;

            if ($indicator < 1) {
                return response()->json([
                    'combined_order_id' => 0,
                    'result' => false,
                    'message' => __('api.order.profit_too_low')
                ]);
            }
        }

        // One transaction: a refused line (out of stock, a coupon that cannot be
        // used, a wallet that cannot pay) takes the whole order back with it,
        // keeping the cart, instead of leaving half an order behind.
        try {
            return DB::transaction(fn () => $this->placeOrder($request, $cartItems, $payFromWallet));
        } catch (DomainException $exception) {
            return response()->json([
                'combined_order_id' => 0,
                'result' => false,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  Collection<int, Cart>  $cartItems
     *
     * @throws DomainException with the message to show the customer when the cart cannot be ordered
     */
    private function placeOrder(Request $request, Collection $cartItems, bool $payFromWallet): JsonResponse
    {
        $user = StoreUser::find(auth()->user()->id);

        $address = Address::where('id', $cartItems->first()->address_id)->first();
        $shippingAddress = [];
        if ($address != null) {
            $shippingAddress['name']        = $user->name;
            $shippingAddress['email']       = $user->email;
            $shippingAddress['address']     = $address->address;
            $shippingAddress['country']     = $address->country?->name;
            $shippingAddress['state']       = $address->state ? $address->state->name : null;
            $shippingAddress['city']        = $address->city ? $address->city->name : null;
            $shippingAddress['postal_code'] = $address->postal_code;
            $shippingAddress['phone']       = $address->phone;
            if ($address->latitude || $address->longitude) {
                $shippingAddress['lat_lang'] = $address->latitude . ',' . $address->longitude;
            }
        }

        $combined_order = new CombinedOrder;
        $combined_order->user_id = $user->id;
        $combined_order->shipping_address = json_encode($shippingAddress);
        $combined_order->save();

        $seller_products = [];
        foreach ($cartItems as $cartItem) {
            $product_ids = [];
            $product = Product::find($cartItem['product_id']);
            if ($product === null) {
                $cartItem->delete();
                continue;
            }
            if (isset($seller_products[$product->user_id])) {
                $product_ids = $seller_products[$product->user_id];
            }
            array_push($product_ids, $cartItem);
            $seller_products[$product->user_id] = $product_ids;
        }

        if ($seller_products === []) {
            throw new DomainException(__('api.cart.empty'));
        }

        $lastOrder = null;
        foreach ($seller_products as $seller_product) {
            $order = new Order;
            $order->combined_order_id = $combined_order->id;
            $order->user_id = $user->id;
            $order->additional_info = $request->additional_info;
            $order->delivery_date = $request->delivery_date;
            $order->shipping_address = $combined_order->shipping_address;

            // $order->shipping_type = $cartItems->first()->shipping_type;
            // if ($cartItems->first()->shipping_type == 'pickup_point') {
            //     $order->pickup_point_id = $cartItems->first()->pickup_point;
            // }
            $order->order_from = 'app';


            // requested by mohammed
            $order->company_address = $user->company_address;
            $order->company_shipping_address = $user->shipping_address;
            $order->company_tax_number = $user->tax_number;
            // end

            // for making order.member_serial = customer serial
            if ($user->is_rep) {
                $user_serial = $seller_product[0]->rep_customer?->member_serial;
            } else {
                $user_serial = $user->member_serial;
            }
            $order->member_serial = $user_serial;

            $order->payment_type = $request->payment_type;
            // orders.shipping_type is NOT NULL with no default, and the order
            // is saved here before the item loop below sets it. The legacy
            // app's non-strict MySQL stored '' meanwhile; this app's strict
            // connection rejects the order instead.
            $order->shipping_type = $seller_product[0]['shipping_type'];
            $order->delivery_viewed = '0';
            $order->payment_status_viewed = '0';
            $order->code = date('Ymd-His') . rand(10, 99);
            $order->date = strtotime('now');
            if ($payFromWallet) {
                $order->payment_status = 'paid';
            } else {
                $order->payment_status = 'unpaid';
            }

            $order->save();

            $subtotal = 0;
            $tax = 0;
            $shipping = 0;
            $coupon_discount = 0;
            $order_deleted_products_info = "";

            //Order Details Storing
            foreach ($seller_product as $cartItem) {
                if ($user->is_rep) {
                    $order->by_rep = $cartItem->by_rep;
                    $order->for_customer = $cartItem->for_customer;
                }
                $product = Product::find($cartItem['product_id']);
                if ($product === null) {
                    $cartItem->delete();
                    continue;
                }

                if (0 == $product->published) {
                    $order_deleted_products_info .= ' -' . $product->ar_name . ', ';
                    $cartItem->delete();
                    continue;
                }
                if ($cartItem['is_price_changed']) {
                    $subtotal +=  $cartItem['rep_price'] * $cartItem['quantity'];
                } else {
                    $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                }

                $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];

                $product_variation = $cartItem['variation'];

                $this->orderPlacementService()->reserveStock($product, $product_variation, $cartItem['quantity']);

                $order_detail = new OrderDetail;
                $order_detail->order_id = $order->id;
                $order_detail->seller_id = $product->user_id;
                $order_detail->product_id = $product->id;
                $order_detail->product_serial = $product->serial;
                $order_detail->product_matId = $product->mat_id;
                $order_detail->product_name = $product->ar_name;
                $order_detail->variation = $product_variation;
                if ($cartItem["is_price_changed"] == true) {
                    $order_detail->price = $cartItem['rep_price'] * $cartItem['quantity'];
                    $order_detail->original_price = cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                    $order_detail->is_price_changed = true;
                } else {
                    $order_detail->price = cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                    $order_detail->original_price = cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                }

                $order_detail->tax = cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
                $order_detail->shipping_type = $cartItem['shipping_type'];
                $order_detail->variation_qty = $cartItem['variation_qty'];
                $order_detail->product_referral_code = $cartItem['product_referral_code'];
                $order_detail->shipping_cost = $cartItem['shipping_cost'];
                $order_detail->product_serial = $product->serial;
                $order_detail->item_notes = $cartItem['item_notes'];
                // order_details.tax is NOT NULL and products.tax nullable: see
                // the web OrderController::store().
                $order_detail->tax = $product->tax ?? 0;
                $order_detail->profits = (float)($order_detail->price * ($product->collection?->amount ?? 1)) / 100;

                $shipping += $order_detail->shipping_cost;

                // if ($cartItem['shipping_type'] == 'pickup_point') {
                //     $order_detail->pickup_point_id = $cartItem['pickup_point'];
                // }
                //End of storing shipping cost
                if (addon_is_activated('club_point')) {
                    $order_detail->earn_point = $product->earn_point;
                }

                $order_detail->quantity = $cartItem['quantity'];
                $order_detail->save();

                $product->increment('num_of_sale', $cartItem['quantity']);

                $order->seller_id = $product->user_id;
                //======== Added By Kiron ==========
                $order->shipping_type = $cartItem['shipping_type'];
                if ($cartItem['shipping_type'] == 'pickup_point') {
                    $order->pickup_point_id = $cartItem['pickup_point'];
                }
                if ($cartItem['shipping_type'] == 'carrier') {
                    $order->carrier_id = $cartItem['carrier_id'];
                }

                if ($product->added_by == 'seller' && $product->user->seller != null) {
                    $seller = $product->user->seller;
                    $seller->num_of_sale += $cartItem['quantity'];
                    $seller->save();
                }

                $subtotal = $this->offerService()->processOfferOnCheckOut($order_detail, $order, $subtotal, $product_variation);


                if (addon_is_activated('affiliate_system')) {
                    if ($order_detail->product_referral_code) {
                        $referred_by_user = StoreUser::where('referral_code', $order_detail->product_referral_code)->first();

                        $affiliateController = new AffiliateController;
                        $affiliateController->processAffiliateStats($referred_by_user->id, 0, $order_detail->quantity, 0, 0);
                    }
                }
            }

            if ($order->orderDetails()->doesntExist()) {
                $order->delete();
                continue;
            }

            $order_new_data_array = $this->orderService()->checkForOtajerProblems($order);

            if ($order_new_data_array['is_down']) {
                $order->member_serial = $order_new_data_array['member_serial'];
                $order->by_rep == 1 ? $order->for_customer = $order_new_data_array['for_customer'] : null;
            }

            $order->grand_total = $subtotal + $tax + $shipping;
            $order->additional_info .= $order_deleted_products_info ? $order_deleted_products_info . 'تمت إزالتهم من سلة التسوق الخاصة بك، المنتجات غير النشطة' : null;

            $coupon_code = collect($seller_product)->pluck('coupon_code')->filter()->first();
            if ($coupon_code != null) {
                $coupon_discount = $this->orderPlacementService()->useCoupon($user->id, $coupon_code, collect($seller_product));
                $order->coupon_discount = $coupon_discount;
                $order->grand_total = max(0, $order->grand_total - $coupon_discount);
            }

            $combined_order->grand_total += $order->grand_total;

            if (str_contains((string) $request->payment_type, "manual_payment_")) { // if payment type like  manual_payment_1 or  manual_payment_25 etc)

                $order->manual_payment = 1;
                $order->save();
            }

            $order->save();
            $order->load('orderDetails');
            $order->profits = $order->get_order_details_profits();
            $order->discount_percent = 100 - (($order->get_order_details_price() / ($order->get_order_details_original_price() == 0 ? 1 : $order->get_order_details_original_price()) * 100));
            $order->indicator = ($order->get_order_details_profits() / ($order->get_order_details_price() == 0 ? 1 : $order->get_order_details_price())) * 100;
            $order->save();

            if (auth()->user()->is_rep) {
                $visit = auth()->user()->rep_last_open_visit();
                if ($visit !== null) {
                    $visit->order_id = $order->id;
                    $visit->save();
                }
            }
            $lastOrder = $order;
        }

        if ($lastOrder === null) {
            throw new DomainException(__('api.cart.empty'));
        }
        $order = $lastOrder;

        $combined_order->save();

        // Charged before the cart is emptied: a wallet that cannot cover the
        // order rolls the whole order back and the cart stays as it was.
        if ($payFromWallet && ! app(WalletPaymentService::class)->charge($user, $combined_order->id)) {
            throw new DomainException(__('storefront.checkout.insufficient_wallet_balance'));
        }

        if ($user->is_rep) {
            $user->last_order_at = Carbon::now()->format('Y-m-d');
            $user->profits += $order->profits;
            $user->save();

            $rep_customer = $seller_product[0]->rep_customer;
            if ($rep_customer !== null) {
                $rep_customer->last_order_at = Carbon::now()->format('Y-m-d');
                $rep_customer->profits += $order->profits;
                $rep_customer->save();
            }
        } else {
            $user->last_order_at = Carbon::now()->format('Y-m-d');
            $user->profits += $order->profits;
            $user->save();
        }

        Cart::where('user_id', auth()->user()->id)->delete();

        if (
            $request->payment_type == 'cash_on_delivery'
            || $request->payment_type == 'wallet'
            || str_contains((string) $request->payment_type, "manual_payment_") // if payment type like  manual_payment_1 or  manual_payment_25 etc
        ) {
            DB::afterCommit(fn () => NotificationUtility::sendOrderPlacedNotification($order));
        }

        // default rep logic
        if (!auth()->user()->is_rep && get_setting('default_representative_check', 0) == 1) {
            $this->orderService()->applyDefaultRepLogic($order, $user);
        }

        return response()->json([
            'combined_order_id' => $combined_order->id,
            'result' => true,
            'message' => __('api.order.placed'),
        ]);
    }
}
