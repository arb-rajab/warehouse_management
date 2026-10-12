<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CombinedOrder;
use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Services\GatewayPaymentService;
use App\Services\SessionGatewayPayment;
use App\Services\VerifiedPayment;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Session;

class AamarpayController extends Controller
{
    public function pay(){
        if (Auth::user()->phone == null) {
            flash(translate('Please add phone number to your profile'))->warning();
            return redirect()->route('profile');
        }

        if (Auth::user()->email == null) {
            $email = 'customer@exmaple.com';
        }
        else{
            $email = Auth::user()->email;
        }

        if (get_setting('aamarpay_sandbox') == 1) {
            $url = 'https://sandbox.aamarpay.com/request.php'; // live url https://secure.aamarpay.com/request.php
        }
        else {
            $url = 'https://secure.aamarpay.com/request.php';
        }

        $amount = 0;
        if(Session::has('payment_type')){
            if(Session::get('payment_type') == 'cart_payment'){
                $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));
                $amount = round($combined_order->grand_total);
            }
            elseif (Session::get('payment_type') == 'wallet_payment') {
                $amount = round(Session::get('payment_data')['amount']);
            }
            elseif (Session::get('payment_type') == 'customer_package_payment') {
                $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);
                $amount = round($customer_package->amount);
            }
            elseif (Session::get('payment_type') == 'seller_package_payment') {
                $seller_package = SellerPackage::findOrFail(Session::get('payment_data')['seller_package_id']);
                $amount = round($seller_package->amount);
            }
        }

        // The transaction id success() checks against aamarpay's search API.
        $tran_id = Str::random(20);
        Session::put('aamarpay_tran_id', $tran_id);

        $fields = [
            'store_id' => env('AAMARPAY_STORE_ID'), //store id will be aamarpay,  contact integration@aamarpay.com for test/live id
            'amount' => $amount, //transaction amount
            'payment_type' => 'VISA', //no need to change
            'currency' => 'BDT',  //currenct will be USD/BDT
            'tran_id' => $tran_id, //transaction id must be unique from your end
            'cus_name' => Auth::user()->name,  //customer name
            'cus_email' => $email, //customer email address
            'cus_add1' => '',  //customer address
            'cus_add2' => '', //customer address
            'cus_city' => '',  //customer city
            'cus_state' => '',  //state
            'cus_postcode' => '', //postcode or zipcode
            'cus_country' => 'Bangladesh',  //country
            'cus_phone' => Auth::user()->phone, //customer phone number
            'cus_fax' => 'Not¬Applicable',  //fax
            'ship_name' => '', //ship name
            'ship_add1' => '',  //ship address
            'ship_add2' => '',
            'ship_city' => '',
            'ship_state' => '',
            'ship_postcode' => '',
            'ship_country' => 'Bangladesh',
            'desc' => config('app.name').' payment',
            'success_url' => route('aamarpay.success'), //your success route
            'fail_url' => route('aamarpay.fail'), //your fail route
            'cancel_url' => route('cart'), //your cancel url
            'opt_a' => Session::get('payment_type'),  //optional paramter
            'opt_b' => Session::get('combined_order_id'),
            'opt_c' => json_encode(Session::get('payment_data')),
            'opt_d' => '',
            'signature_key' => env('AAMARPAY_SIGNATURE_KEY'), //signature key will provided aamarpay, contact integration@aamarpay.com for test/live signature key
        ];

        $fields_string = http_build_query($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $url_forward = str_replace('"', '', stripslashes(curl_exec($ch)));

        $this->redirect_to_merchant($url_forward);
    }

    function redirect_to_merchant($url) {
        if (get_setting('aamarpay_sandbox') == 1) {
            $base_url = 'https://sandbox.aamarpay.com/';
        }
        else {
            $base_url = 'https://secure.aamarpay.com/';
        }

        ?>
        <html xmlns="http://www.w3.org/1999/xhtml">
          <head><script type="text/javascript">
            function closethisasap() { document.forms["redirectpost"].submit(); }
          </script></head>
          <body onLoad="closethisasap();">

            <form name="redirectpost" method="post" action="<?php echo $base_url.$url; ?>"></form>

          </body>
        </html>
        <?php
        exit;
    }


    /**
     * aamarpay posts the customer back here, and the post used to be trusted for
     * everything: the payment type, the order (`opt_b`) and the top-up amount
     * (`opt_c`). The transaction must be the one this session started, and
     * aamarpay's search API (by `mer_txnid`) must report it paid for at least the
     * amount due; what it reports is credited to the signed-in customer, once.
     */
    public function success(Request $request){
        $tran_id = (string) Session::get('aamarpay_tran_id');
        $amountDue = app(SessionGatewayPayment::class)->amountDue();

        if ($tran_id === '' || $amountDue === null || ! hash_equals($tran_id, (string) $request->input('mer_txnid'))) {
            return app(SessionGatewayPayment::class)->notVerified();
        }

        $transaction = $this->searchTransaction($tran_id);
        $isPaid = is_array($transaction)
            && ($transaction['pay_status'] ?? null) === 'Successful'
            && hash_equals($tran_id, (string) ($transaction['mer_txnid'] ?? ''))
            && GatewayPaymentService::covers((float) ($transaction['amount'] ?? 0), $amountDue);

        if (! $isPaid) {
            return app(SessionGatewayPayment::class)->notVerified();
        }

        Session::forget('aamarpay_tran_id');

        return app(SessionGatewayPayment::class)->complete(new VerifiedPayment('aamarpay', $tran_id, (float) $transaction['amount'], json_encode($transaction)));
    }

    /**
     * The transaction as aamarpay's own server reports it.
     *
     * @return array<string, mixed>|null
     */
    private function searchTransaction(string $tran_id): ?array
    {
        $base_url = get_setting('aamarpay_sandbox') == 1 ? 'https://sandbox.aamarpay.com/' : 'https://secure.aamarpay.com/';

        $response = Http::timeout(30)->get($base_url.'api/v1/trxcheck/request.php', [
            'request_id' => $tran_id,
            'store_id' => env('AAMARPAY_STORE_ID'),
            'signature_key' => env('AAMARPAY_SIGNATURE_KEY'),
            'type' => 'json',
        ]);

        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }

    public function fail(Request $request){
        flash(translate('Payment failed'))->error();
    	return redirect()->route('cart');
    }
}
