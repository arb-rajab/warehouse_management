<?php


namespace App\Http\Controllers\Api\V2;


use App\Services\ApiGatewayPayment;
use App\Services\GatewayPaymentService;
use App\Services\VerifiedPayment;
use App\Utility\NagadUtility;
use Illuminate\Http\Request;

class NagadController
{
    /** @var array{user_id: int, payment_type: string, combined_order_id: int|null, package_id: int|null, amount: float}|null */
    private $payment = null;

    private $amount = null;
    private $tnx = null;

    private $nagadHost;
    private $tnx_status = false;

    private $merchantAdditionalInfo = [];

    public function __construct()
    {
        date_default_timezone_set('Asia/Dhaka');
        if (config('nagad.sandbox_mode') === 'sandbox') {
            $this->nagadHost = "http://sandbox.mynagad.com:10080/";
        } else {
            $this->nagadHost = "https://api.mynagad.com/";
        }
    }

    /**
     * The payer is the signed-in customer and the amount their own order's total
     * or the package's price, from the database; verify() pays exactly what is
     * recorded here (the request's `user_id` and, except for a top-up, `amount`
     * are not read).
     */
    public function begin(Request $request)
    {
        $this->payment = app(ApiGatewayPayment::class)->resolve($request);

        if ($this->payment === null) {
            return response()->json(['data' => null, 'result' => false, 'url' => '', 'message' => translate('Nothing to pay')]);
        }

        $this->amount = $this->payment['amount'];
        $this->tnx_status = false;
        $this->tnx = $this->payment['combined_order_id'] ?? rand(10000, 99999);

        return $this->getSession($this->payment['payment_type']);
    }


    public function getSession($payment_type)
    {

        $DateTime = Date('YmdHis');
        $MerchantID = config('nagad.merchant_id');
        //$invoice_no = 'Inv'.Date('YmdH').rand(1000, 10000);
        $invoice_no = $this->tnx_status ? $this->tnx : 'Inv' . Date('ymdHis') . random_int(1000, 9999);
        $merchantCallbackURL = route('app.nagad.callback_url', ['payment_type' => $payment_type]);

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

        // What verify() will pay for this Nagad order, fixed now, by the signed-in payer.
        app(GatewayPaymentService::class)->begin('nagad', $invoice_no, $this->payment['user_id'], $this->payment['payment_type'], $this->payment['combined_order_id'], $this->payment['package_id'], $this->payment['amount']);

        $ur = $this->nagadHost . "api/dfs/check-out/initialize/" . $MerchantID . "/" . $invoice_no;
        $Result_Data = NagadUtility::HttpPostMethod($ur, $PostData);

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
                    if ($this->tnx !== '') {
                        $this->merchantAdditionalInfo['tnx_id'] = $this->tnx;
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

                    $OrderSubmitUrl = $this->nagadHost . "api/dfs/check-out/complete/" . $paymentReferenceId;
                    $Result_Data_Order = NagadUtility::HttpPostMethod($OrderSubmitUrl, $PostDataOrder);
                    //dd($Result_Data_Order);
                    if ($Result_Data_Order['status'] == "Success") {
                        return response()->json([
                            'data' => $Result_Data_Order,
                            'result' => true,
                            'url' => $Result_Data_Order['callBackUrl'],
                            'message' => translate('Redirect Url is found')
                        ]);
                    } else {
                        return response()->json([
                            'data' => $Result_Data_Order,
                            'result' => false,
                            'url' => '',
                            'message' => translate('Could not generate payment link')
                        ]);
                    }
                } else {
                    return response()->json([
                        'data' => $PlainResponse,
                        'result' => false,
                        'url' => '',
                        'message' => translate('Payment reference id or challenge is missing')
                    ]);
                }
            } else {
                return response()->json([
                    'data' => null,
                    'result' => false,
                    'url' => '',
                    'message' => translate('Sensitive data or Signature is empty')
                ]);
            }
        } else {
            return response()->json([
                'data' => null,
                'result' => false,
                'url' => '',
                'message' => translate('Sensitive data or Signature is missing')
            ]);
        }
    }

    /**
     * Nagad redirects the app's web view here. `payment_ref_id` is read from the
     * request (it used to be cut out of $_SERVER['REQUEST_URI'] by position), and
     * Nagad's server must report the payment successful; it then pays the order
     * begin() recorded under Nagad's `orderId`, once, when the amount covers it.
     * The app's own `process` call, which credited whatever it was sent with no
     * sign-in, is gone.
     */
    public function verify(Request $request, $payment_type)
    {
        $payment_ref_id = (string) $request->input('payment_ref_id');
        $json = $payment_ref_id === '' ? '' : NagadUtility::HttpGet($this->nagadHost . "api/dfs/verify/payment/" . rawurlencode($payment_ref_id));
        $result = json_decode((string) $json, true);

        $isPaid = is_array($result)
            && ($result['status'] ?? null) === 'Success'
            && hash_equals($payment_ref_id, (string) ($result['paymentRefId'] ?? ''))
            && app(GatewayPaymentService::class)->completePending(new VerifiedPayment('nagad', (string) ($result['orderId'] ?? ''), (float) ($result['amount'] ?? 0), $json));

        if ($isPaid) {
            return response()->json([
                'result' => true,
                'message' => translate('Payment is successful'),
                'payment_details' => $json
            ]);
        }
        return response()->json([
            'result' => false,
            'message' => translate('Payment failed !'),
            'payment_details' => ''
        ]);
    }
}
