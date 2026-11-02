<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AffiliateController;
use App\Mail\InvoiceEmailManager;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CombinedOrder;
use App\Models\DeliveryHistory;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariation;
use App\Models\SmsTemplate;
use App\Models\StoreUser;
use App\Services\OfferService;
use App\Services\OrderPlacementService;
use App\Services\OrderService;
use App\Utility\NotificationUtility;
use App\Utility\SmsUtility;
use Auth;
use Carbon\Carbon;
use DB;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mail;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class OrderController extends Controller implements HasMiddleware
{
    protected $offerService;
    protected $orderService;

    /**
     * Get the middleware that should be assigned to the controller.
     *
     * See Auth\LoginController::middleware()'s docblock for why this
     * replaces the constructor-based $this->middleware() call.
     *
     * @return array<int, \Illuminate\Routing\Controllers\Middleware>
     */
    public static function middleware(): array
    {
        return [

        ];
    }

    protected function orderPlacementService(): OrderPlacementService
    {
        return app(OrderPlacementService::class);
    }

    protected function offerService()
    {
        return app(OfferService::class);
    }

    protected function orderService()
    {
        return app(OrderService::class);
    }

    public function store(Request $request)
    {
        $user = StoreUser::find(Auth::user()->id);

        // Everything below is one transaction: a refused line (out of stock, a
        // coupon that cannot be used) takes the whole order back with it,
        // instead of leaving the earlier sellers' orders and their stock
        // deductions behind.
        try {
            DB::transaction(fn () => $this->placeOrder($request, $user));
        } catch (DomainException $exception) {
            flash($exception->getMessage())->warning();
            return redirect()->route('cart');
        }
    }

    /**
     * @throws DomainException with the message to show the customer when the cart cannot be ordered
     */
    private function placeOrder(Request $request, StoreUser $user): void
    {
        $carts = Cart::with('rep_customer')->where('user_id', $user->id)
            ->lockForUpdate()
            ->get();

        if ($carts->isEmpty()) {
            throw new DomainException(__('storefront.cart.empty'));
        }

        $address = Address::where('id', $carts[0]['address_id'])->first();

        $shippingAddress = [];
        if ($address != null) {
            $shippingAddress['name']        = $user->name;
            $shippingAddress['email']       = $user->email;
            $shippingAddress['address']     = $address->address;
            $shippingAddress['country']     = $address->country?->name;
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

        $seller_products = [];
        if ($carts[0]->by_rep) {
            $for_customer_id = $carts[0]->for_customer;
        }
        foreach ($carts as $cartItem) {
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
            throw new DomainException(__('storefront.cart.empty'));
        }

        $lastOrder = null;
        foreach ($seller_products as $seller_product) {
            $order = new Order;
            $order->combined_order_id = $combined_order->id;
            $order->user_id = $user->id;
            $order->order_from = 'web';

            // for making order.member_serial = customer serial
            if ($user->is_rep) {
                $user_serial = $seller_product[0]->rep_customer?->member_serial;
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

            // orders.shipping_type is NOT NULL with no default. The legacy
            // app ran MySQL non-strict, which quietly stored '' when this
            // line was commented out; this app's connection is strict, and
            // rejects the order instead. Every web cart ships home_delivery.
            $order->shipping_type = $carts[0]['shipping_type'];
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
                if ($product === null) {
                    $cartItem->delete();
                    continue;
                }
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

                $this->orderPlacementService()->reserveStock($product, $product_variation, $cartItem['quantity']);

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
                // order_details.tax is NOT NULL and products.tax nullable: the
                // legacy app's non-strict MySQL stored 0 for a product with no
                // tax, which this app's strict connection refuses to do for it.
                $order_detail->tax = $product->tax ?? 0;
                $order_detail->item_notes = $cartItem['item_notes'];
                $order_detail->profits = (float)($order_detail->price * $product->collection?->amount ?? 0) / 100;


                $shipping += $order_detail->shipping_cost;
                //End of storing shipping cost

                $order_detail->quantity = $cartItem['quantity'];

                // if (addon_is_activated('club_point')) {
                //     $order_detail->earn_point = $product->earn_point;
                // }

                $order_detail->save();

                $product->increment('num_of_sale', $cartItem['quantity']);

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
                //         $referred_by_user = StoreUser::where('referral_code', $order_detail->product_referral_code)->first();

                //         $affiliateController = new AffiliateController;
                //         $affiliateController->processAffiliateStats($referred_by_user->id, 0, $order_detail->quantity, 0, 0);
                //     }
                // }
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
            $order->additional_info .= $order_deleted_products_info ? $order_deleted_products_info . ' تمت إزالتهم من سلة التسوق الخاصة بك، المنتجات غير النشطة' : null;

            $coupon_code = collect($seller_product)->pluck('coupon_code')->filter()->first();
            if ($coupon_code != null) {
                $coupon_discount = $this->orderPlacementService()->useCoupon($user->id, $coupon_code, collect($seller_product));
                $order->coupon_discount = $coupon_discount;
                $order->grand_total = max(0, $order->grand_total - $coupon_discount);
            }

            $combined_order->grand_total += $order->grand_total;

            $order->save();
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
            throw new DomainException(__('storefront.cart.empty'));
        }
        $order = $lastOrder;

        $combined_order->save();

        // foreach($combined_order->orders as $order){
        //     NotificationUtility::sendOrderPlacedNotification($order);
        // }

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

        $request->session()->put('combined_order_id', $combined_order->id);

        // default rep logic
        if (!auth()->user()->is_rep && get_setting('default_representative_check', 0) == 1) {
            $this->orderService()->applyDefaultRepLogic($order, $user);
        }
    }

}
