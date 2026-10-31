<?php
namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SSLCommerz;
use App\Models\CombinedOrder;
use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Services\SessionGatewayPayment;
use App\Services\VerifiedPayment;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Session;

session_start();

class SslcommerzController extends Controller
{
    public function pay(Request $request)
    {
        # Here you have to receive all the order data to initate the payment.
        # Lets your oder trnsaction informations are saving in a table called "orders"
        # In orders table order uniq identity is "order_id","order_status" field contain status of the transaction, "grand_total" is the order amount to be paid and "currency" is for storing Site Currency which will be checked with paid currency.
        if(Session::has('payment_type')){
            // One transaction id per payment, kept in the session for success() to
            // check: the old ids were md5s of the order or user id, the same for
            // every top-up a customer made.
            $tran_id = Str::random(20);
            $request->session()->put('sslcommerz_tran_id', $tran_id);

            if(Session::get('payment_type') == 'cart_payment'){
                $combined_order = CombinedOrder::findOrFail($request->session()->get('combined_order_id'));
                $post_data = [];
                $post_data['total_amount'] = $combined_order->grand_total; # You cant not pay less than 10
                $post_data['currency'] = "BDT";
                $post_data['tran_id'] = $tran_id;

                $post_data['value_a'] = $post_data['tran_id'];
                $post_data['value_b'] = $request->session()->get('combined_order_id');
                $post_data['value_c'] = $request->session()->get('payment_type');

                #Start to save these value  in session to pick in success page.
                // $_SESSION['payment_values']['tran_id']=$post_data['tran_id'];
                // $_SESSION['payment_values']['order_id']=$request->session()->get('order_id');
                // $_SESSION['payment_values']['payment_type']=$request->session()->get('payment_type');
                #End to save these value  in session to pick in success page.

                # CUSTOMER INFORMATION
                // $post_data['cus_name'] = $request->session()->get('shipping_info')['name'];
                // $post_data['cus_add1'] = $request->session()->get('shipping_info')['address'];
                // $post_data['cus_city'] = $request->session()->get('shipping_info')['city'];
                // $post_data['cus_postcode'] = $request->session()->get('shipping_info')['postal_code'];
                // $post_data['cus_country'] = $request->session()->get('shipping_info')['country'];
                // $post_data['cus_phone'] = $request->session()->get('shipping_info')['phone'];
                // $post_data['cus_email'] = $request->session()->get('shipping_info')['email'];

            }
            elseif (Session::get('payment_type') == 'wallet_payment') {
                $post_data = [];
                $post_data['total_amount'] = $request->session()->get('payment_data')['amount']; # You cant not pay less than 10
                $post_data['currency'] = "BDT";
                $post_data['tran_id'] = $tran_id;

                $post_data['value_a'] = $post_data['tran_id'];
                $post_data['value_b'] = $request->session()->get('payment_data')['amount'];
                $post_data['value_c'] = $request->session()->get('payment_type');
                $post_data['value_d'] = Auth::user()->id;

                #Start to save these value  in session to pick in success page.
                // $_SESSION['payment_values']['tran_id']=$post_data['tran_id'];
                // $_SESSION['payment_values']['payment_data']=$request->session()->get('payment_data');
                // $_SESSION['payment_values']['payment_type']=$request->session()->get('payment_type');
                #End to save these value  in session to pick in success page.

            }
            elseif (Session::get('payment_type') == 'customer_package_payment') {
                $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);
                $post_data = [];
                $post_data['total_amount'] = $customer_package->amount; # You cant not pay less than 10
                $post_data['currency'] = "BDT";
                $post_data['tran_id'] = $tran_id;

                $post_data['value_a'] = $post_data['tran_id'];
                $post_data['value_b'] = $request->session()->get('payment_data')['customer_package_id'];
                $post_data['value_c'] = $request->session()->get('payment_type');
                $post_data['value_d'] = Auth::user()->id;

                #Start to save these value  in session to pick in success page.
                // $_SESSION['payment_values']['tran_id']=$post_data['tran_id'];
                // $_SESSION['payment_values']['payment_data']=$request->session()->get('payment_data');
                // $_SESSION['payment_values']['payment_type']=$request->session()->get('payment_type');
                #End to save these value  in session to pick in success page.
            }
            elseif (Session::get('payment_type') == 'seller_package_payment') {
                $seller_package = SellerPackage::findOrFail(Session::get('payment_data')['seller_package_id']);
                $post_data = [];
                $post_data['total_amount'] = $seller_package->amount; # You cant not pay less than 10
                $post_data['currency'] = "BDT";
                $post_data['tran_id'] = $tran_id;

                $post_data['value_a'] = $post_data['tran_id'];
                $post_data['value_b'] = $request->session()->get('payment_data')['seller_package_id'];
                $post_data['value_c'] = $request->session()->get('payment_type');
                $post_data['value_d'] = Auth::user()->id;

                #Start to save these value  in session to pick in success page.
                // $_SESSION['payment_values']['tran_id']=$post_data['tran_id'];
                // $_SESSION['payment_values']['payment_data']=$request->session()->get('payment_data');
                // $_SESSION['payment_values']['payment_type']=$request->session()->get('payment_type');
                #End to save these value  in session to pick in success page.

            }

            # CUSTOMER INFORMATION
            $user = Auth::user();
            $post_data['cus_name'] = $user->name;
            $post_data['cus_add1'] = $user->address;
            $post_data['cus_city'] = $user->city;
            $post_data['cus_postcode'] = $user->postal_code;
            $post_data['cus_country'] = $user->country;
            $post_data['cus_phone'] = $user->phone;
            $post_data['cus_email'] = $user->email;
        }

        $server_name=$request->root()."/";
        $post_data['success_url'] = $server_name . "sslcommerz/success";
        $post_data['fail_url'] = $server_name . "sslcommerz/fail";
        $post_data['cancel_url'] = $server_name . "sslcommerz/cancel";
        // dd($post_data);
        # SHIPMENT INFORMATION
        // $post_data['ship_name'] = 'ship_name';
        // $post_data['ship_add1 '] = 'Ship_add1';
        // $post_data['ship_add2'] = "";
        // $post_data['ship_city'] = "";
        // $post_data['ship_state'] = "";
        // $post_data['ship_postcode'] = "";
        // $post_data['ship_country'] = "Bangladesh";

        # OPTIONAL PARAMETERS
        // $post_data['value_a'] = "ref001";
        // $post_data['value_b'] = "ref002";
        // $post_data['value_c'] = "ref003";
        // $post_data['value_d'] = "ref004";

        $sslc = new SSLCommerz();

        # initiate(Transaction Data , false: Redirect to SSLCOMMERZ gateway/ true: Show all the Payement gateway here )
        $payment_options = $sslc->initiate($post_data, false);
        if (!is_array($payment_options)) {
            print_r($payment_options);
            $payment_options = [];
        }

    }

    /**
     * SSLCommerz posts the customer back here. The post itself proves nothing:
     * it named the order, the amount and (for a top-up or a package) the account
     * to sign in as, and was trusted, so anyone could post one. The transaction
     * must be the one this session started, and SSLCommerz's validation server
     * must confirm it for the amount due (orderValidate); what it confirms is
     * credited to the signed-in customer, once.
     */
    public function success(Request $request)
    {
        $tran_id = (string) Session::get('sslcommerz_tran_id');
        $amountDue = app(SessionGatewayPayment::class)->amountDue();

        if ($tran_id === '' || $amountDue === null || ! hash_equals($tran_id, (string) $request->input('tran_id'))) {
            return app(SessionGatewayPayment::class)->notVerified();
        }

        $sslc = new SSLCommerz();
        if ($sslc->orderValidate($tran_id, $amountDue, 'BDT', $request->all()) !== true) {
            return app(SessionGatewayPayment::class)->notVerified();
        }

        Session::forget('sslcommerz_tran_id');
        $result = $sslc->getResultData();

        return app(SessionGatewayPayment::class)->complete(new VerifiedPayment('sslcommerz', $tran_id, (float) ($result->amount ?? 0), json_encode($result)));
    }

    public function fail(Request $request)
    {
        $request->session()->forget('order_id');
        $request->session()->forget('payment_data');
        flash(__('storefront.checkout.payment_failed'))->warning();
        return redirect()->route('home');
    }

     public function cancel(Request $request)
    {
        $request->session()->forget('order_id');
        $request->session()->forget('payment_data');
        flash(__('storefront.checkout.payment_cancelled'))->error();
    	return redirect()->route('home');
    }
}
