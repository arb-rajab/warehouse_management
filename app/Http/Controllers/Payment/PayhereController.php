<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CombinedOrder;
use App\Models\CustomerPackage;
use App\Services\GatewayPaymentService;
use App\Services\VerifiedPayment;
use App\Utility\PayhereUtility;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Session;

class PayhereController extends Controller
{
    private $security_key;

    public function pay(Request $request){
        if(Session::has('payment_type')){
            if(Session::get('payment_type') == 'cart_payment'){
                $combined_order = CombinedOrder::findOrFail($request->session()->get('combined_order_id'));
                $combined_order_id = $combined_order->id;
                $amount = $combined_order->grand_total;
                $first_name = json_decode($combined_order->shipping_address)->name;
                $last_name = 'X';
                $phone = json_decode($combined_order->shipping_address)->phone;
                $email = json_decode($combined_order->shipping_address)->email;
                $address = json_decode($combined_order->shipping_address)->address;
                $city = json_decode($combined_order->shipping_address)->city;
                return PayhereUtility::create_checkout_form($combined_order_id, $amount, $first_name, $last_name, $phone, $email, $address, $city);
            }

            elseif (Session::get('payment_type') == 'wallet_payment') {
                $user_id = Auth::user()->id;
                // The account rides in the signed order_id; custom_1 is not signed.
                $order_id = 'W'.$user_id.'-'.Str::random(10);
                $amount = $request->amount;
                $first_name = Auth::user()->name;
                $last_name = 'X';
                $phone = '123456789';
                $email = Auth::user()->email;
                $address = 'dummy address';
                $city = 'Colombo';

                return PayhereUtility::create_wallet_form($user_id, $order_id, $amount, $first_name, $last_name, $phone, $email, $address, $city);
            }
            elseif (Session::get('payment_type') == 'customer_package_payment') {
                $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);
                $user_id = Auth::user()->id;
                $package_id = $customer_package->id;
                // The account and the package ride in the signed order_id; custom_1 and custom_2 are not signed.
                $order_id = 'P'.$user_id.'-'.$package_id.'-'.Str::random(10);
                $amount = $customer_package->amount;
                $first_name = Auth::user()->name;
                $last_name = 'X';
                $phone = '123456789';
                $email = Auth::user()->email;
                $address = 'dummy address';
                $city = 'Colombo';

                return PayhereUtility::create_customer_package_form($user_id, $package_id, $order_id, $amount, $first_name, $last_name, $phone, $email, $address, $city);
            }
        }
    }
    public function checkout_testing()
    {
        $order_id = random_int(100000, 999999);
        $amount = 88.00;
        $first_name = 'Hasan';
        $last_name = 'Taluker';
        $phone = '2135421321';
        $email = 'hasan@taluker.com';
        $address = '22/b baker street';
        $city = 'Colombo';

        return PayhereUtility::create_checkout_form($order_id, $amount, $first_name, $last_name, $phone, $email, $address, $city);
    }

    public function wallet_testing()
    {
        $order_id = random_int(100000, 999999);
        $user_id = Auth::user()->id;
        $amount = 88.00;
        $first_name = 'Hasan';
        $last_name = 'Taluker';
        $phone = '2135421321';
        $email = 'hasan@taluker.com';
        $address = '22/b baker street';
        $city = 'Colombo';

        return PayhereUtility::create_wallet_form($user_id, $order_id, $amount, $first_name, $last_name, $phone, $email, $address, $city);
    }

    public function customer_package_payment_testing()
    {
        $order_id = random_int(100000, 999999);
        $user_id = Auth::user()->id;
        $package_id = 4;
        $amount = 88.00;
        $first_name = 'Hasan';
        $last_name = 'Taluker';
        $phone = '2135421321';
        $email = 'hasan@taluker.com';
        $address = '22/b baker street';
        $city = 'Colombo';

        return PayhereUtility::create_customer_package_form($user_id,$package_id, $order_id, $amount, $first_name, $last_name, $phone, $email, $address, $city);
    }


    //sample response
    /*
     {
       "merchant_id":"1215091",
       "order_id":"196696714",
       "payment_id":"320025078020",
       "payhere_amount":"99.00",
       "payhere_currency":"LKR",
       "status_code":"2",
       "md5sig":"F889DBDF7BF987529C77096E465B749B",
       "custom_1":"788392",
       "custom_2":"",
       "status_message":"Successfully completed the payment.",
       "method":"TEST",
       "card_holder_name":"ddd",
       "card_no":"************1292",
       "card_expiry":"1221",
       "recurring":"0"
    }
    */


    //checkout related functions ------------------------------------<starts>
    /**
     * Whether a PayHere notification is for this merchant, signed with its
     * secret, and reports a completed payment.
     *
     * The three notify handlers each rebuilt the signature from
     * config('services.payhere.secret'), which is null once config is cached, so the
     * "secret" was empty and anyone could sign a payment-complete call for any
     * order. They also took the merchant id from the request. With no secret or
     * merchant id configured this refuses everything, and it compares in
     * constant time.
     *
     * @param  array<string, mixed>  $post
     */
    private static function notificationIsAuthentic(array $post): bool
    {
        $secret = (string) config('services.payhere.secret');
        $merchantId = (string) config('services.payhere.merchant_id');

        if ($secret === '' || $merchantId === '') {
            return false;
        }

        $expected = strtoupper(md5(
            ($post['merchant_id'] ?? '').($post['order_id'] ?? '').($post['payhere_amount'] ?? '')
            .($post['payhere_currency'] ?? '').($post['status_code'] ?? '').strtoupper(md5($secret))
        ));

        return hash_equals($merchantId, (string) ($post['merchant_id'] ?? ''))
            && hash_equals($expected, strtoupper((string) ($post['md5sig'] ?? '')))
            && (string) ($post['status_code'] ?? '') === '2';
    }

    /**
     * The combined order is the signed `order_id` (the checkout form sets it to
     * the combined order's id); `custom_1` is not signed and is not read.
     */
    public static function checkout_notify()
    {
        if (self::notificationIsAuthentic($_POST) && ctype_digit((string) $_POST['order_id'])) {
            return PayhereController::checkout_success((int) $_POST['order_id'], $_POST);
        }

        return PayhereController::checkout_incomplete();
    }

    public static function checkout_return()
    {
        Session::put('cart', collect([]));
        Session::forget('payment_type');
        Session::forget('delivery_info');
        Session::forget('coupon_id');
        Session::forget('coupon_discount');

        flash(translate('Payment process completed'))->success();
        return redirect()->route('order_confirmed');
    }

    public static function checkout_cancel()
    {
        return PayhereController::checkout_incomplete();
    }

    /**
     * Pay the combined order once, when the signed amount pays its total: the
     * notification comes from PayHere's server with no session, so the payer is
     * the order's own customer, and `payment_id` is recorded so a repeated
     * notification pays nothing.
     */
    public static function checkout_success($combined_order_id,$responses)
    {
        $payment = self::verifiedPayment($responses);
        $combined_order = CombinedOrder::find($combined_order_id);

        if ($payment === null || $combined_order === null
            || ! app(GatewayPaymentService::class)->complete($payment, (int) $combined_order->user_id, GatewayPaymentService::CART, $combined_order->id)) {
            return PayhereController::checkout_incomplete();
        }

        return response('', 200);
    }

    /**
     * The payment an authentic notification reports, under PayHere's own
     * `payment_id`; null when it carries none.
     *
     * @param  array<string, mixed>  $post
     */
    private static function verifiedPayment(array $post): ?VerifiedPayment
    {
        $payment_id = (string) ($post['payment_id'] ?? '');

        return $payment_id === '' ? null : new VerifiedPayment('payhere', $payment_id, (float) $post['payhere_amount'], json_encode($post));
    }

    public static function checkout_incomplete()
    {
        Session::forget('order_id');
        flash(translate("Incomplete"))->error();
        //flash(translate('Payment failed'))->error();
        //dd($response_text);
        return redirect()->route('home')->send();
    }
    //checkout related functions ------------------------------------<ends>

    //wallet related functions ------------------------------------<starts>
    /**
     * The account is in the signed `order_id` (`W<user id>-<random>`, see pay());
     * `custom_1` is not signed and is not read.
     */
    public static function wallet_notify()
    {
        if (self::notificationIsAuthentic($_POST) && preg_match('/^W(\d+)-/', (string) $_POST['order_id'], $matches)) {
            return PayhereController::wallet_success((int) $matches[1], $_POST['payhere_amount'], $_POST);
        }

        return PayhereController::wallet_incomplete();
    }

    public static function wallet_return()
    {
        Session::forget('payment_data');
        Session::forget('payment_type');

        flash(translate('Payment process completed'))->success();
        return redirect()->route('wallet.index');
    }

    public static function wallet_cancel()
    {
        return PayhereController::wallet_incomplete();
    }

    /**
     * Top up the account by the signed amount, once per PayHere `payment_id`
     * (GatewayPaymentService locks the account, checks it exists and increments
     * the balance).
     */
    public static function wallet_success($id,$amount,$payment_details)
    {
        $payment = self::verifiedPayment($payment_details);

        if ($payment === null || ! app(GatewayPaymentService::class)->complete($payment, (int) $id, GatewayPaymentService::WALLET)) {
            return PayhereController::wallet_incomplete();
        }

        return response('', 200);
    }

    public static function wallet_incomplete()
    {
        Session::forget('payment_data');
        flash(translate('Payment Incomplete'))->error();
        return redirect()->route('home')->send();
    }
    //wallet related functions ------------------------------------<ends>

    //customer package related functions ------------------------------------<starts>
    /**
     * The account and the package are in the signed `order_id`
     * (`P<user id>-<package id>-<random>`, see pay()); `custom_1` and `custom_2`
     * are not signed and are not read.
     */
    public static function customer_package_notify()
    {
        if (self::notificationIsAuthentic($_POST) && preg_match('/^P(\d+)-(\d+)-/', (string) $_POST['order_id'], $matches)) {
            return PayhereController::customer_package_success((int) $matches[1], (int) $matches[2], $_POST);
        }

        return PayhereController::customer_package_incomplete();
    }

    public static function customer_package_return()
    {
        Session::forget('payment_data');
        flash(translate('Payment process completed'))->success();
        return redirect()->route('dashboard');
    }

    public static function customer_package_cancel()
    {
        return PayhereController::customer_package_incomplete();
    }

    /**
     * Activate the package once per PayHere `payment_id`, when the signed amount
     * pays its price (read from the database).
     */
    public static function customer_package_success($id,$customer_package_id,$payment_details)
    {
        $payment = self::verifiedPayment($payment_details);

        if ($payment === null || ! app(GatewayPaymentService::class)->complete($payment, (int) $id, GatewayPaymentService::CUSTOMER_PACKAGE, null, (int) $customer_package_id)) {
            return PayhereController::customer_package_incomplete();
        }

        return response('', 200);
    }

    public static function customer_package_incomplete()
    {
        Session::forget('payment_data');
        flash(translate("Payment Incomplete"))->error();
        return redirect()->route('home')->send();
    }
    //customer package related functions ------------------------------------<ends>
}
