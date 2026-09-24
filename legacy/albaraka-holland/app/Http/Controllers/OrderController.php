<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Http\Controllers\AffiliateController;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Address;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\OrderDetail;
use App\Models\CouponUsage;
use App\Models\Coupon;
use App\Models\User;
use App\Models\CombinedOrder;
use App\Models\SmsTemplate;
use App\Models\ProductVariation;
use Auth;
use Mail;
use App\Mail\InvoiceEmailManager;
use App\Services\OfferService;
use App\Services\OrderService;
use App\Utility\NotificationUtility;
// use CoreComponentRepository;
use App\Utility\SmsUtility;
use DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class OrderController extends Controller
{
    protected $offerService;
    protected $orderService;

    public function __construct()
    {
        // Staff Permission Check
        $this->middleware(['permission:view_all_orders|view_inhouse_orders|view_seller_orders|view_pickup_point_orders'])->only('all_orders');
        $this->middleware(['permission:view_order_details'])->only('show');
        $this->middleware(['permission:delete_order'])->only('destroy', 'bulk_order_delete');
    }

    protected function offerService()
    {
        return app(OfferService::class);
    }

    protected function orderService()
    {
        return app(OrderService::class);
    }

    // All Orders
    public function all_orders(Request $request)
    {
        // CoreComponentRepository::instantiateShopRepository();

        $date = $request->date;
        $sort_search = null;
        $delivery_status = null;
        $payment_status = '';

        $orders = Order::orderBy('id', 'desc');
        $admin_user_id = User::where('user_type', 'admin')->first()->id;


        if (
            Route::currentRouteName() == 'inhouse_orders.index' &&
            Auth::user()->can('view_inhouse_orders')
        ) {
            $orders = $orders->where('orders.seller_id', '=', $admin_user_id);
        } else if (
            Route::currentRouteName() == 'seller_orders.index' &&
            Auth::user()->can('view_seller_orders')
        ) {
            $orders = $orders->where('orders.seller_id', '!=', $admin_user_id);
        } else if (
            Route::currentRouteName() == 'pick_up_point.index' &&
            Auth::user()->can('view_pickup_point_orders')
        ) {
            $orders->where('shipping_type', 'pickup_point')->orderBy('code', 'desc');
            if (
                Auth::user()->user_type == 'staff' &&
                Auth::user()->staff->pick_up_point != null
            ) {
                $orders->where('shipping_type', 'pickup_point')
                    ->where('pickup_point_id', Auth::user()->staff->pick_up_point->id);
            }
        } else if (
            Route::currentRouteName() == 'all_orders.index' &&
            Auth::user()->can('view_all_orders')
        ) {
        } else {
            abort(403);
        }

        if ($request->search) {
            $sort_search = $request->search;

            $orders = $orders
                ->where('code', 'like', '%' . $sort_search . '%')
                ->OrWhere('code', 'like', '%' . $sort_search . '%')
                ->OrWhere(function ($query) use ($sort_search) {
                    $query->where(function ($q) use ($sort_search) {
                        // Case when 'by_rep' is 1 → use 'for_customer'
                        $q->where('orders.by_rep', 1)
                            ->whereExists(function ($subQuery) use ($sort_search) {
                                $subQuery->select(DB::raw(1))
                                    ->from('users')
                                    ->whereColumn('orders.for_customer', 'users.id')
                                    ->where('users.AccSysID', 'like', '%' . $sort_search . '%');
                            });
                    })->orWhere(function ($q) use ($sort_search) {
                        // Case when 'by_rep' is 0 → use 'user_id'
                        $q->where('orders.by_rep', 0)
                            ->whereExists(function ($subQuery) use ($sort_search) {
                                $subQuery->select(DB::raw(1))
                                    ->from('users')
                                    ->whereColumn('orders.user_id', 'users.id')
                                    ->where('users.AccSysID', 'like', '%' . $sort_search . '%');
                            });
                    });
                });
        }
        if ($request->payment_status != null) {
            $orders = $orders->where('payment_status', $request->payment_status);
            $payment_status = $request->payment_status;
        }
        if ($request->delivery_status != null) {
            $orders = $orders->where('delivery_status', $request->delivery_status);
            $delivery_status = $request->delivery_status;
        }
        if ($date != null) {
            $orders = $orders->where('created_at', '>=', date('Y-m-d', strtotime(explode(" to ", $date)[0])) . '  00:00:00')
                ->where('created_at', '<=', date('Y-m-d', strtotime(explode(" to ", $date)[1])) . '  23:59:59');
        }
        $orders = $orders->paginate(15);
        return view('backend.sales.index', compact('orders', 'sort_search', 'payment_status', 'delivery_status', 'date'));
    }

    public function show($id)
    {
        $order = Order::findOrFail(decrypt($id));
        $order_shipping_address = json_decode($order->shipping_address);
        $delivery_boys = User::where('city', '$order_shipping_address->city')
            ->where('user_type', 'delivery_boy')
            ->get();

        $order->viewed = 1;
        $order->save();

        // return $order->orderDetails;

        $cartResults = [];

        foreach ($order->orderDetails as $cart) {
            if (isset($cart->variation_qty) && $cart->variation_qty != 'null') {
                $jsonData = $cart->variation_qty;

                $variationData = json_decode($jsonData, true);
                $results = [];
                $cartResults[$cart->product_serial] = $variationData;
            }
        }
        // return $cartResults;
        return view('backend.sales.show', compact('order', 'delivery_boys', 'cartResults'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = User::find(Auth::user()->id);
        $carts = Cart::where('user_id', $user->id)
            ->get();

        if ($carts->isEmpty()) {
            flash(translate('Your cart is empty'))->warning();
            return redirect()->route('home');
        }

        $address = Address::where('id', $carts[0]['address_id'])->first();

        $shippingAddress = [];
        if ($address != null) {
            $shippingAddress['name']        = $user->name;
            $shippingAddress['email']       = $user->email;
            $shippingAddress['address']     = $address->address;
            $shippingAddress['country']     = $address->country->name;
            $shippingAddress['state']       = $address->state ? $address->state->name : null;
            $shippingAddress['city']        = $address->city  ? $address->city->name : null;
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

        $seller_products = array();
        if ($carts[0]->by_rep) {
            $for_customer_id = $carts[0]->for_customer;
        }
        foreach ($carts as $cartItem) {
            $product_ids = array();
            $product = Product::find($cartItem['product_id']);
            if (isset($seller_products[$product->user_id])) {
                $product_ids = $seller_products[$product->user_id];
            }
            array_push($product_ids, $cartItem);
            $seller_products[$product->user_id] = $product_ids;
        }

        foreach ($seller_products as $seller_product) {
            $order = new Order;
            $order->combined_order_id = $combined_order->id;
            $order->user_id = $user->id;
            $order->order_from = 'web';

            // for making order.member_serial = customer serial
            if ($user->is_rep) {
                $user_serial = $seller_product[0]->rep_customer->member_serial;
            } else {
                $user_serial = $user->member_serial;
            }
            $order->member_serial = $user_serial;

            // requested by mohammed
            $order->company_address = $user->company_address;
            $order->company_shipping_address = $user->shipping_address;
            $order->company_tax_number = $user->tax_number;
            // end

            $order->shipping_address = $combined_order->shipping_address;

            $order->additional_info = $request->additional_info;

            // $order->shipping_type = $carts[0]['shipping_type'];
            // if ($carts[0]['shipping_type'] == 'pickup_point') {
            //     $order->pickup_point_id = $cartItem['pickup_point'];
            // }
            // if ($carts[0]['shipping_type'] == 'carrier') {
            //     $order->carrier_id = $cartItem['carrier_id'];
            // }

            if (isset($for_customer_id)) {
                $order->by_rep = true;
                $order->for_customer = $for_customer_id;
            }
            $order->payment_type = $request->payment_option;
            $order->delivery_viewed = '0';
            $order->payment_status_viewed = '0';
            $order->code = date('Ymd-His') . rand(10, 99);
            $order->date = strtotime('now');
            $order->save();

            $subtotal = 0;
            $tax = 0;
            $shipping = 0;
            $coupon_discount = 0;
            $order_deleted_products_info = "";

            $original_price = 0;
            $rep_price = 0;
            //Order Details Storing
            foreach ($seller_product as $cartItem) {
                $product = Product::find($cartItem['product_id']);
                $product_stocks = ProductStock::where('product_id', $product->id);
                if (0 == $product->published) {
                    $order_deleted_products_info .= ' -' . $product->ar_name . ', ';
                    $cartItem->delete();
                    $request->session()->put('delete_product', true);
                    continue;
                }
                if ($cartItem['is_price_changed'] == true) {
                    $subtotal += $cartItem['rep_price'] * $cartItem['quantity'];
                } else {
                    $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                }

                //  $tax +=  cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
                //  $coupon_discount += $cartItem['discount'];

                $product_variation = $cartItem['variation'];

                $product_stock = $product_stocks->where('variant', 'like', '%' . $product_variation . '%')->first();
                if ($product->digital != 1 && $cartItem['quantity'] > $product_stock->qty) {
                    flash(translate('The requested quantity is not available for ') . $product->getTranslation('name'))->warning();
                    $order->delete();
                    return redirect()->route('cart')->send();
                } elseif ($product->digital != 1) {
                    $product_stock->qty -= $cartItem['quantity'];
                    $product_stock->save();
                }

                $order_detail = new OrderDetail;
                $order_detail->order_id = $order->id;
                $order_detail->seller_id = $product->user_id;
                $order_detail->product_serial = $product->serial;
                $order_detail->product_matId = $product->mat_id;
                $order_detail->product_name = $product->ar_name;
                $order_detail->product_id = $product->id;
                $order_detail->variation = $product_variation;
                $order_detail->variation_qty = $cartItem['variation_qty'];
                if ($cartItem['is_price_changed'] == true) {
                    $order_detail->price = $cartItem['rep_price'] * $cartItem['quantity'];
                    $order_detail->original_price = cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                    $order_detail->is_price_changed = true;

                    $original_price += cart_product_price($cartItem, $product, false, false);
                    $rep_price += $cartItem['rep_price'] * $cartItem['quantity'];
                } else {
                    $order_detail->price = cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                    $order_detail->original_price = cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];

                    $original_price += cart_product_price($cartItem, $product, false, false);
                    $rep_price += cart_product_price($cartItem, $product, false, false);
                }
                $order_detail->tax = cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
                $order_detail->shipping_type = $cartItem['shipping_type'];
                $order_detail->product_referral_code = $cartItem['product_referral_code'];
                $order_detail->shipping_cost = $cartItem['shipping_cost'];
                $order_detail->product_serial = $product->serial;
                $order_detail->tax = $product->tax;
                $order_detail->item_notes = $cartItem['item_notes'];
                $order_detail->profits = (float)($order_detail->price * $product->collection?->amount ?? 0) / 100;


                $shipping += $order_detail->shipping_cost;
                //End of storing shipping cost

                $order_detail->quantity = $cartItem['quantity'];

                // if (addon_is_activated('club_point')) {
                //     $order_detail->earn_point = $product->earn_point;
                // }

                $order_detail->save();

                $product->num_of_sale += $cartItem['quantity'];
                $product->save();

                $order->seller_id = $product->user_id;
                $order->shipping_type = $cartItem['shipping_type'];
                $order->delivery_date = $request->delivery_date ?? null;

                // if ($cartItem['shipping_type'] == 'pickup_point') {
                //     $order->pickup_point_id = $cartItem['pickup_point'];
                // }
                // if ($cartItem['shipping_type'] == 'carrier') {
                //     $order->carrier_id = $cartItem['carrier_id'];
                // }

                if ($product->added_by == 'seller' && $product->user->seller != null) {
                    $seller = $product->user->seller;
                    $seller->num_of_sale += $cartItem['quantity'];
                    $seller->save();
                }

                // if (addon_is_activated('affiliate_system')) {
                //     if ($order_detail->product_referral_code) {
                //         $referred_by_user = User::where('referral_code', $order_detail->product_referral_code)->first();

                //         $affiliateController = new AffiliateController;
                //         $affiliateController->processAffiliateStats($referred_by_user->id, 0, $order_detail->quantity, 0, 0);
                //     }
                // }
            }
            $order_new_data_array = $this->orderService()->checkForOtajerProblems($order);

            if ($order_new_data_array['is_down']) {
                $order->member_serial = $order_new_data_array['member_serial'];
                $order->by_rep == 1 ? $order->for_customer = $order_new_data_array['for_customer'] : null;
            }
            $order->grand_total = $subtotal + $tax + $shipping;
            $order->additional_info .= $order_deleted_products_info ? $order_deleted_products_info . ' تمت إزالتهم من سلة التسوق الخاصة بك، المنتجات غير النشطة' : null;

            if ($seller_product[0]->coupon_code != null) {
                $order->coupon_discount = $coupon_discount;
                $order->grand_total -= $coupon_discount;

                $coupon_usage = new CouponUsage;
                $coupon_usage->user_id = Auth::user()->id;
                $coupon_usage->coupon_id = Coupon::where('code', $seller_product[0]->coupon_code)->first()->id;
                $coupon_usage->save();
            }

            $combined_order->grand_total += $order->grand_total;

            $order->save();
            $order->profits = $order->get_order_details_profits();
            $order->discount_percent = 100 - (($order->get_order_details_price() / ($order->get_order_details_original_price() == 0 ? 1 : $order->get_order_details_original_price()) * 100));
            $order->indicator = ($order->get_order_details_profits() / ($order->get_order_details_price() == 0 ? 1 : $order->get_order_details_price())) * 100;
            $order->save();
            if (auth()->user()->is_rep) {
                $visit = auth()->user()->rep_last_open_visit();
                $visit->order_id = $order->id;
                $visit->save();
            }
        }

        $combined_order->save();

        // foreach($combined_order->orders as $order){
        //     NotificationUtility::sendOrderPlacedNotification($order);
        // }

        if ($user->is_rep) {
            $user->last_order_at = Carbon::now()->format('Y-m-d');
            $user->profits += $order->profits;
            $user->save();

            $rep_customer = $seller_product[0]->rep_customer;
            $rep_customer->last_order_at = Carbon::now()->format('Y-m-d');
            $rep_customer->profits += $order->profits;
            $rep_customer->save();
        } else {
            $user->last_order_at = Carbon::now()->format('Y-m-d');
            $user->profits += $order->profits;
            $user->save();
        }

        $request->session()->put('combined_order_id', $combined_order->id);

        // default rep logic
        if (!auth()->user()->is_rep && get_setting('default_representative_check', 0) == 1) {
            $this->orderService()->applyDefaultRepLogic($order, $user);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */


    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        if ($order != null) {
            foreach ($order->orderDetails as $key => $orderDetail) {
                try {

                    $product_stock = ProductStock::where('product_id', $orderDetail->product_id)->where('variant', $orderDetail->variation)->first();
                    if ($product_stock != null) {
                        $product_stock->qty += $orderDetail->quantity;
                        $product_stock->save();
                    }
                } catch (\Exception $e) {
                }

                $orderDetail->delete();
            }
            $order->delete();
            flash(translate('Order has been deleted successfully'))->success();
        } else {
            flash(translate('Something went wrong'))->error();
        }
        return back();
    }

    public function bulk_order_delete(Request $request)
    {
        if ($request->id) {
            foreach ($request->id as $order_id) {
                $this->destroy($order_id);
            }
        }

        return 1;
    }

    public function order_details(Request $request)
    {
        $order = Order::findOrFail($request->order_id);
        $order->save();
        return view('seller.order_details_seller', compact('order'));
    }

    public function update_delivery_status(Request $request)
    {
        $order = Order::findOrFail($request->order_id);
        $order->delivery_viewed = '0';
        $order->delivery_status = $request->status;
        $order->save();

        if ($request->status == 'cancelled' && $order->payment_type == 'wallet') {
            $user = User::where('id', $order->user_id)->first();
            $user->balance += $order->grand_total;
            $user->save();
        }

        if (Auth::user()->user_type == 'seller') {
            foreach ($order->orderDetails->where('seller_id', Auth::user()->id) as $key => $orderDetail) {
                $orderDetail->delivery_status = $request->status;
                $orderDetail->save();

                if ($request->status == 'cancelled') {
                    $variant = $orderDetail->variation;
                    if ($orderDetail->variation == null) {
                        $variant = '';
                    }

                    $product_stock = ProductStock::where('product_id', $orderDetail->product_id)
                        ->where('variant', $variant)
                        ->first();

                    if ($product_stock != null) {
                        $product_stock->qty += $orderDetail->quantity;
                        $product_stock->save();
                    }
                }
            }
        } else {
            foreach ($order->orderDetails as $key => $orderDetail) {

                $orderDetail->delivery_status = $request->status;
                $orderDetail->save();

                if ($request->status == 'cancelled') {
                    $variant = $orderDetail->variation;
                    if ($orderDetail->variation == null) {
                        $variant = '';
                    }

                    $product_stock = ProductStock::where('product_id', $orderDetail->product_id)
                        ->where('variant', $variant)
                        ->first();

                    if ($product_stock != null) {
                        $product_stock->qty += $orderDetail->quantity;
                        $product_stock->save();
                    }
                }

                if (addon_is_activated('affiliate_system')) {
                    if (($request->status == 'delivered' || $request->status == 'cancelled') &&
                        $orderDetail->product_referral_code
                    ) {

                        $no_of_delivered = 0;
                        $no_of_canceled = 0;

                        if ($request->status == 'delivered') {
                            $no_of_delivered = $orderDetail->quantity;
                        }
                        if ($request->status == 'cancelled') {
                            $no_of_canceled = $orderDetail->quantity;
                        }

                        $referred_by_user = User::where('referral_code', $orderDetail->product_referral_code)->first();

                        $affiliateController = new AffiliateController;
                        $affiliateController->processAffiliateStats($referred_by_user->id, 0, 0, $no_of_delivered, $no_of_canceled);
                    }
                }
            }
        }
        if (addon_is_activated('otp_system') && SmsTemplate::where('identifier', 'delivery_status_change')->first()->status == 1) {
            try {
                SmsUtility::delivery_status_change(json_decode($order->shipping_address)->phone, $order);
            } catch (\Exception $e) {
            }
        }

        //sends Notifications to user
        NotificationUtility::sendNotification($order, $request->status);
        if (get_setting('google_firebase') == 1 && $order->user->device_token != null) {
            $request->device_token = $order->user->device_token;
            $request->title = "Order updated !";
            $status = str_replace("_", "", $order->delivery_status);
            $request->text = " Your order {$order->code} has been {$status}";

            $request->type = "order";
            $request->id = $order->id;
            $request->user_id = $order->user->id;

            NotificationUtility::sendFirebaseNotification($request);
        }


        if (addon_is_activated('delivery_boy')) {
            if (Auth::user()->user_type == 'delivery_boy') {
                $deliveryBoyController = new DeliveryBoyController;
                $deliveryBoyController->store_delivery_history($order);
            }
        }

        return 1;
    }

    public function update_tracking_code(Request $request)
    {
        $order = Order::findOrFail($request->order_id);
        $order->tracking_code = $request->tracking_code;
        $order->save();

        return 1;
    }

    public function update_payment_status(Request $request)
    {
        $order = Order::findOrFail($request->order_id);
        $order->payment_status_viewed = '0';
        $order->save();

        if (Auth::user()->user_type == 'seller') {
            foreach ($order->orderDetails->where('seller_id', Auth::user()->id) as $key => $orderDetail) {
                $orderDetail->payment_status = $request->status;
                $orderDetail->save();
            }
        } else {
            foreach ($order->orderDetails as $key => $orderDetail) {
                $orderDetail->payment_status = $request->status;
                $orderDetail->save();
            }
        }

        $status = 'paid';
        foreach ($order->orderDetails as $key => $orderDetail) {
            if ($orderDetail->payment_status != 'paid') {
                $status = 'unpaid';
            }
        }
        $order->payment_status = $status;
        $order->save();


        if (
            $order->payment_status == 'paid' &&
            $order->commission_calculated == 0
        ) {
            calculateCommissionAffilationClubPoint($order);
        }

        //sends Notifications to user
        NotificationUtility::sendNotification($order, $request->status);
        if (get_setting('google_firebase') == 1 && $order->user->device_token != null) {
            $request->device_token = $order->user->device_token;
            $request->title = "Order updated !";
            $status = str_replace("_", "", $order->payment_status);
            $request->text = " Your order {$order->code} has been {$status}";

            $request->type = "order";
            $request->id = $order->id;
            $request->user_id = $order->user->id;

            NotificationUtility::sendFirebaseNotification($request);
        }


        if (addon_is_activated('otp_system') && SmsTemplate::where('identifier', 'payment_status_change')->first()->status == 1) {
            try {
                SmsUtility::payment_status_change(json_decode($order->shipping_address)->phone, $order);
            } catch (\Exception $e) {
            }
        }
        return 1;
    }

    public function assign_delivery_boy(Request $request)
    {
        if (addon_is_activated('delivery_boy')) {

            $order = Order::findOrFail($request->order_id);
            $order->assign_delivery_boy = $request->delivery_boy;
            $order->delivery_history_date = date("Y-m-d H:i:s");
            $order->save();

            $delivery_history = \App\Models\DeliveryHistory::where('order_id', $order->id)
                ->where('delivery_status', $order->delivery_status)
                ->first();

            if (empty($delivery_history)) {
                $delivery_history = new \App\Models\DeliveryHistory;

                $delivery_history->order_id = $order->id;
                $delivery_history->delivery_status = $order->delivery_status;
                $delivery_history->payment_type = $order->payment_type;
            }
            $delivery_history->delivery_boy_id = $request->delivery_boy;

            $delivery_history->save();

            if (env('MAIL_USERNAME') != null && get_setting('delivery_boy_mail_notification') == '1') {
                $array['view'] = 'emails.invoice';
                $array['subject'] = translate('You are assigned to delivery an order. Order code') . ' - ' . $order->code;
                $array['from'] = env('MAIL_FROM_ADDRESS');
                $array['order'] = $order;

                try {
                    Mail::to($order->delivery_boy->email)->queue(new InvoiceEmailManager($array));
                } catch (\Exception $e) {
                }
            }

            if (addon_is_activated('otp_system') && SmsTemplate::where('identifier', 'assign_delivery_boy')->first()->status == 1) {
                try {
                    SmsUtility::assign_delivery_boy($order->delivery_boy->phone, $order->code);
                } catch (\Exception $e) {
                }
            }
        }

        return 1;
    }

    public function update_manager_notes(Request $request)
    {
        $order = Order::find($request->id);
        $order->manager_notes = $request->notes;
        if ($order->save()) {
            return 1;
        }
        return 0;
    }



    // this function might cause some bugs if multivendor is activated, idk.
    public function edit_item_price(Request $request)
    { // let the admin change item's price for placed orders
        // The Algorithm
        // 1. get the order item
        // 2. change its price
        // 3. recalculate the order grand_total
        // 4. recalculate the combined_order grand total // Actually, no need for this step. CombinedOrders are used when multivendor is acitve. but i will do it anyway, let's hope it won't break anything in the future


        $item = OrderDetail::find($request->id);
        $admin_price = $request->admin_price;
        $admin_price  = number_format($admin_price, 2, '.', '');
        if ($item) {

            $item->price = $admin_price * $item->quantity; //2. change its price
            $item->save();


            $order = Order::find($item->order_id);
            $old_total = $order->grand_total;
            $order->grand_total = $order->orderDetails->sum('price'); // 3. recalculate the order grand_total
            $order->save();


            $combinedOrder = CombinedOrder::find($order->combined_order_id);
            $combinedOrder->grand_total = ($combinedOrder->grand_total - $old_total) + $order->grand_total; //4. recalculate the combined_order grand total
            $combinedOrder->save();
            return 1;
        } else {
            return 0;
        }
    }
}
