<?php
namespace App\Http\Controllers\Payment;

use App\Models\CombinedOrder;
use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Services\GatewayPaymentService;
use App\Services\SessionGatewayPayment;
use App\Services\VerifiedPayment;
use App\Utility\NagadUtility;
use Illuminate\Http\Request;
use Session;

class NagadController{

    private $amount = null;
    private $tnx = null;

    private $nagadHost;
    private $tnx_status = false;

    private $merchantAdditionalInfo = [];

    public function __construct()
    {
        date_default_timezone_set('Asia/Dhaka');
        if (config('nagad.sandbox_mode') === 'sandbox') {
            $this->nagadHost = "http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/";
        }else{
            $this->nagadHost = "https://api.mynagad.com/";
        }

    }

    public function tnx($id,$status=false)
    {
        $this->tnx = $id;
        $this->tnx_status = $status;
        return $this;
    }

    public function amount($amount)
    {
        $this->amount = $amount;
        return $this;
    }

    public function pay()
    {
        if(Session::has('payment_type')){
            if(Session::get('payment_type') == 'cart_payment'){
                $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));
                $this->amount($combined_order->grand_total);
                $this->tnx($combined_order->id);
            }
            if(Session::get('payment_type') == 'wallet_payment'){
                $this->amount(round(Session::get('payment_data')['amount']));
                $this->tnx(rand(000000,999999));
            }
            if(Session::get('payment_type') == 'customer_package_payment'){
                $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);
                $this->amount(round($customer_package->amount));
                $this->tnx(rand(000000,999999));
            }
            if(Session::get('payment_type') == 'seller_package_payment'){
                $seller_package = SellerPackage::findOrFail(Session::get('payment_data')['seller_package_id']);
                $this->amount(round($seller_package->amount));
                $this->tnx(rand(000000,999999));
            }
        }

        $DateTime = Date('YmdHis');
        $MerchantID = config('nagad.merchant_id');
        //$invoice_no = 'Inv'.Date('YmdH').rand(1000, 10000);
        $invoice_no = $this->tnx_status ? $this->tnx :'Inv'.Date('YmdH').rand(1000, 10000);
        $merchantCallbackURL = config('nagad.callback_url');

        $SensitiveData = [
            'merchantId' => $MerchantID,
            'datetime' => $DateTime,
            'orderId' => $invoice_no,
            'challenge' => NagadUtility::generateRandomString(),
        ];

        $PostData = [
            'accountNumber' => config('nagad.merchant_number'), //optional
            'dateTime' => $DateTime,
            'sensitiveData' => NagadUtility::EncryptDataWithPublicKey(json_encode($SensitiveData)),
            'signature' => NagadUtility::SignatureGenerate(json_encode($SensitiveData)),
        ];

        // The only Nagad order verify() accepts for this session.
        Session::put('nagad_order_id', $invoice_no);

        $ur = $this->nagadHost."api/dfs/check-out/initialize/" . $MerchantID . "/" . $invoice_no;
        $Result_Data = NagadUtility::HttpPostMethod($ur,$PostData);

        if (isset($Result_Data['sensitiveData']) && isset($Result_Data['signature'])) {
            if ($Result_Data['sensitiveData'] != "" && $Result_Data['signature'] != "") {

                $PlainResponse = json_decode(NagadUtility::DecryptDataWithPrivateKey($Result_Data['sensitiveData']), true);

                if (isset($PlainResponse['paymentReferenceId']) && isset($PlainResponse['challenge'])) {

                    $paymentReferenceId = $PlainResponse['paymentReferenceId'];
                    $randomserver = $PlainResponse['challenge'];

                    $SensitiveDataOrder = [
                        'merchantId' => $MerchantID,
                        'orderId' => $invoice_no,
                        'currencyCode' => '050',
                        'amount' => $this->amount,
                        'challenge' => $randomserver,
                    ];


                    // $merchantAdditionalInfo = '{"no_of_seat": "1", "Service_Charge":"20"}';
                    if($this->tnx !== ''){
                        $this->merchantAdditionalInfo['tnx_id'] =  $this->tnx;
                    }
                    // echo $merchantAdditionalInfo;
                    // exit();

                    $PostDataOrder = [
                        'sensitiveData' => NagadUtility::EncryptDataWithPublicKey(json_encode($SensitiveDataOrder)),
                        'signature' => NagadUtility::SignatureGenerate(json_encode($SensitiveDataOrder)),
                        'merchantCallbackURL' => $merchantCallbackURL,
                        'additionalMerchantInfo' => (object)$this->merchantAdditionalInfo,
                    ];

                    // echo json_encode($PostDataOrder);
                    // exit();

                    $OrderSubmitUrl = $this->nagadHost."api/dfs/check-out/complete/" . $paymentReferenceId;
                    $Result_Data_Order = NagadUtility::HttpPostMethod($OrderSubmitUrl, $PostDataOrder);
                    try {
                        if ($Result_Data_Order['status'] == "Success") {
                            $url = ($Result_Data_Order['callBackUrl']);
                            return redirect($url);
                            //echo "<script>window.open('$url', '_self')</script>";
                        }
                        else {
                            echo json_encode($Result_Data_Order);
                        }
                    } catch (\Exception $e) {
                        dd($Result_Data_Order);
                    }

                } else {
                    echo json_encode($PlainResponse);
                }
            }
        }

    }

    /**
     * Nagad sends the customer back with `payment_ref_id` in the query string,
     * which used to be cut out of $_SERVER['REQUEST_URI'] by its position, and any
     * successful payment then paid whatever this session was paying for. Nagad's
     * server must report the payment successful, for the order this session
     * created and for at least the amount due, and a payment completes one
     * payment only.
     */
    public function verify(Request $request){
        $payment_ref_id = (string) $request->input('payment_ref_id');
        $order_id = (string) Session::get('nagad_order_id');
        $amountDue = app(SessionGatewayPayment::class)->amountDue();

        if ($payment_ref_id === '' || $order_id === '' || $amountDue === null) {
            return app(SessionGatewayPayment::class)->notVerified();
        }

        $json = NagadUtility::HttpGet($this->nagadHost."api/dfs/verify/payment/" . rawurlencode($payment_ref_id));
        $result = json_decode((string) $json, true);
        $isPaid = is_array($result)
            && ($result['status'] ?? null) === 'Success'
            && hash_equals($order_id, (string) ($result['orderId'] ?? ''))
            && hash_equals($payment_ref_id, (string) ($result['paymentRefId'] ?? ''))
            && GatewayPaymentService::covers((float) ($result['amount'] ?? 0), $amountDue);

        if (! $isPaid) {
            flash(__('storefront.checkout.payment_failed'))->error();
            return redirect()->route('home');
        }

        Session::forget('nagad_order_id');

        return app(SessionGatewayPayment::class)->complete(new VerifiedPayment('nagad', $payment_ref_id, (float) $result['amount'], $json));
    }
}
