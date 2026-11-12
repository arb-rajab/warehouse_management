<?php
namespace App\Http\Controllers\Payment;

use App\Services\GatewayCurrency;
use App\Services\GatewayPaymentService;
use App\Services\SessionGatewayPayment;
use App\Services\VerifiedPayment;
use App\Support\StorePaymentGateways;
use App\Utility\NagadUtility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Session;

class NagadController{

    private $amount = null;
    private $tnx = null;

    private $nagadHost;
    private $tnx_status = false;

    private $merchantAdditionalInfo = [];

    public function __construct()
    {
        if (StorePaymentGateways::get('nagad', 'mode') === 'sandbox') {
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

    /**
     * Nagad's own clock: its protocol timestamps are Dhaka time. Only these timestamps are, so the
     * zone is given here and PHP's default (which the app's own timestamps use) is left alone.
     */
    private static function dhaka(string $format): string
    {
        return now('Asia/Dhaka')->format($format);
    }

    /**
     * Start the payment this session is making. The amount due comes from the database (an
     * order of the signed-in customer, a package's price, the top-up asked for), is converted to
     * BDT, which is the only currency Nagad charges in, and kept to the cent. A currency the store
     * has no active rate for, or a Nagad that does not answer as expected, is refused with a
     * message and nothing is kept.
     */
    public function pay()
    {
        $due = app(SessionGatewayPayment::class)->amountDue();
        $currency = GatewayCurrency::forGateway('nagad');
        $converted = $due === null ? null : $currency->fromStore($due);

        if ($converted === null || $currency->chargeCode() !== 'BDT') {
            Log::error('Nagad refused: nothing due, or no active BDT rate (Nagad charges in BDT only).', ['currency' => $currency->chargeCode()]);

            return $this->failed();
        }

        $this->amount(GatewayCurrency::format($converted));
        $this->tnx(Session::get('payment_type') === 'cart_payment' ? (int) Session::get('combined_order_id') : random_int(100000, 999999));

        $DateTime = self::dhaka('YmdHis');
        $MerchantID = StorePaymentGateways::get('nagad', 'merchant_id');
        $invoice_no = $this->tnx_status ? $this->tnx : 'Inv'.self::dhaka('YmdH').rand(1000, 10000);
        $merchantCallbackURL = self::merchantCallbackUrl();

        $SensitiveData = [
            'merchantId' => $MerchantID,
            'datetime' => $DateTime,
            'orderId' => $invoice_no,
            'challenge' => NagadUtility::generateRandomString(),
        ];

        $PostData = [
            'accountNumber' => StorePaymentGateways::get('nagad', 'merchant_number'), //optional
            'dateTime' => $DateTime,
            'sensitiveData' => NagadUtility::EncryptDataWithPublicKey(json_encode($SensitiveData)),
            'signature' => NagadUtility::SignatureGenerate(json_encode($SensitiveData)),
        ];

        $Result_Data = NagadUtility::HttpPostMethod($this->nagadHost.'api/dfs/check-out/initialize/'.$MerchantID.'/'.$invoice_no, $PostData);

        if (empty($Result_Data['sensitiveData']) || empty($Result_Data['signature'])) {
            return $this->unanswered('initialize', $Result_Data);
        }

        $PlainResponse = json_decode(NagadUtility::DecryptDataWithPrivateKey($Result_Data['sensitiveData']), true);

        if (! isset($PlainResponse['paymentReferenceId']) || ! isset($PlainResponse['challenge'])) {
            return $this->unanswered('reference', $PlainResponse);
        }

        $SensitiveDataOrder = [
            'merchantId' => $MerchantID,
            'orderId' => $invoice_no,
            'currencyCode' => '050', // BDT
            'amount' => $this->amount,
            'challenge' => $PlainResponse['challenge'],
        ];

        if ($this->tnx !== '') {
            $this->merchantAdditionalInfo['tnx_id'] = $this->tnx;
        }

        $PostDataOrder = [
            'sensitiveData' => NagadUtility::EncryptDataWithPublicKey(json_encode($SensitiveDataOrder)),
            'signature' => NagadUtility::SignatureGenerate(json_encode($SensitiveDataOrder)),
            'merchantCallbackURL' => $merchantCallbackURL,
            'additionalMerchantInfo' => (object) $this->merchantAdditionalInfo,
        ];

        $Result_Data_Order = NagadUtility::HttpPostMethod($this->nagadHost.'api/dfs/check-out/complete/'.$PlainResponse['paymentReferenceId'], $PostDataOrder);

        if (($Result_Data_Order['status'] ?? null) !== 'Success' || empty($Result_Data_Order['callBackUrl'])) {
            return $this->unanswered('complete', $Result_Data_Order);
        }

        // The only Nagad order verify() accepts for this session.
        Session::put('nagad_order_id', $invoice_no);

        return redirect($Result_Data_Order['callBackUrl']);
    }

    /** @param  mixed  $response  what Nagad answered, kept in the log, never shown to the customer */
    private function unanswered(string $step, $response)
    {
        Log::error('Nagad did not answer as expected.', ['step' => $step, 'response' => $response]);

        return $this->failed();
    }

    private function failed()
    {
        flash(__('storefront.checkout.payment_failed'))->error();

        return redirect()->route('cart');
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
        $currency = GatewayCurrency::forGateway('nagad');
        $reported = is_array($result) ? (float) ($result['amount'] ?? 0) : 0.0;
        $isPaid = is_array($result)
            && ($result['status'] ?? null) === 'Success'
            && hash_equals($order_id, (string) ($result['orderId'] ?? ''))
            && hash_equals($payment_ref_id, (string) ($result['paymentRefId'] ?? ''))
            && ($currency->isStoreCurrency() ? GatewayPaymentService::covers($reported, $amountDue) : $currency->matchesDue($reported, $amountDue));

        if (! $isPaid) {
            flash(__('storefront.checkout.payment_failed'))->error();
            return redirect()->route('home');
        }

        Session::forget('nagad_order_id');

        return app(SessionGatewayPayment::class)->complete(new VerifiedPayment('nagad', $payment_ref_id, $currency->paidInStoreCurrency($reported, $amountDue), $json));
    }

    /**
     * Where Nagad sends the customer back: `NAGAD_CALLBACK_URL` when set, else the
     * storefront's own callback route, which carries the /temp prefix a hand-built
     * `APP_URL/nagad/callback` did not.
     */
    public static function merchantCallbackUrl(): string
    {
        return config('nagad.callback_url') ?: route('nagad.callback');
    }
}
