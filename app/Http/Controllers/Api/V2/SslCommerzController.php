<?php


namespace App\Http\Controllers\Api\V2;


use App\Services\ApiGatewayPayment;
use App\Models\BusinessSetting;
use App\Models\GatewayPayment;
use App\Services\GatewayPaymentService;
use App\Services\VerifiedPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
# IF BROWSE FROM LOCAL HOST, KEEP true
if (!defined("SSLCZ_IS_LOCAL_HOST")) {
    define("SSLCZ_IS_LOCAL_HOST", true);
}

class SslCommerzController extends Controller
{
    public $sslc_mode;
    public $sslc_data;
    public $store_id;
    public $store_pass;
    public $error = '';

    public function __construct()
    {
        $this->store_id = env('SSLCZ_STORE_ID');
        $this->store_pass = env('SSLCZ_STORE_PASSWD');
    }

    /**
     * Resolved on use rather than in the constructor: the sandbox flag lives in
     * `business_settings`, and building a controller (route:list, wayfinder:generate)
     * must not need that table to exist yet.
     */
    private function sslcMode(): string
    {
        if ($this->sslc_mode === null) {
            $this->setSSLCommerzMode(BusinessSetting::where('type', 'sslcommerz_sandbox')->first()?->value == 1);
        }

        return $this->sslc_mode;
    }

    /**
     * The payer is the signed-in customer and the amount their own order's total
     * or the package's price, from the database (the request's `amount` was
     * charged even for an order). What payment_success() will credit is recorded
     * now, under a transaction id of its own.
     */
    public function begin(Request $request)
    {
        $payment = app(ApiGatewayPayment::class)->resolve($request);

        if ($payment === null) {
            return response()->json(['result' => false, 'url' => '', 'message' => translate('Nothing to pay')]);
        }

        $post_data = [];
        $post_data['total_amount'] = $payment['amount']; # You cant not pay less than 10
        $post_data['currency'] = "BDT";
        $post_data['tran_id'] = 'AIZ-' . Str::random(20); // tran_id must be unique

        app(GatewayPaymentService::class)->begin('sslcommerz', $post_data['tran_id'], $payment['user_id'], $payment['payment_type'], $payment['combined_order_id'], $payment['package_id'], $payment['amount']);

        $post_data['value_a'] = $payment['user_id'];
        $post_data['value_b'] = $payment['combined_order_id'] ?? $payment['package_id'];
        $post_data['value_c'] = $payment['payment_type'];
        $post_data['value_d'] = $payment['amount'];

        # CUSTOMER INFORMATION
        $post_data['cus_name'] = "Customer Name";
        $post_data['cus_add1'] = "Customer Address";
        $post_data['cus_city'] = "Customer City";
        $post_data['cus_postcode'] = "1234";
        $post_data['cus_country'] = "Bangladesh";
        $post_data['cus_phone'] = "123456123";
        $post_data['cus_email'] = "some@mail.com";


        $post_data['success_url'] = url("api/v2/sslcommerz/success");
        $post_data['fail_url'] = url("api/v2/sslcommerz/fail");
        $post_data['cancel_url'] = url("api/v2/sslcommerz/cancel");

        return $this->initiate($post_data);
    }

    /**
     * SSLCommerz posts the app's web view back here. The post's `value_a`..`value_d`
     * (the account, the order or package, the type, the amount) used to be
     * credited as they came. Now the transaction must be one begin() recorded,
     * SSLCommerz's validation server must confirm it for the amount recorded
     * (sslcommerz_validate), and it pays what begin() recorded, once.
     */
    public function payment_success(Request $request)
    {
        $tran_id = (string) $request->input('tran_id');
        $pending = GatewayPayment::query()->where('gateway', 'sslcommerz')->where('reference', $tran_id)->whereNull('completed_at')->first();

        if ($tran_id === '' || $pending === null || $this->sslcommerz_validate($tran_id, (float) $pending->amount, 'BDT', $request->all()) !== true) {
            return response()->json(['result' => false, 'message' => translate('Payment Failed')]);
        }

        $verified = new VerifiedPayment('sslcommerz', $tran_id, (float) ($this->sslc_data->amount ?? 0), json_encode($this->sslc_data));
        if (! app(GatewayPaymentService::class)->completePending($verified)) {
            return response()->json(['result' => false, 'message' => translate('Payment Failed')]);
        }

        return response()->json(['result' => true, 'message' => translate("Payment is successful")]);
    }

    public function payment_process(Request $request)
    {
    }

    public function payment_fail(Request $request)
    {
        return response()->json([
            'result' => false,
            'message' => translate('Payment Failed')
        ]);
    }

    public function payment_cancel(Request $request)
    {
        return response()->json([
            'result' => false,
            'message' => translate('Payment Cancelled')
        ]);
    }


    public function initiate($post_data)
    {
        /*return response()->json([
            'post_data' => json_encode($post_data),
            'result' => false,
            'url' => '',
            'message' => "gg",
        ]);*/

        if ($post_data != '' && is_array($post_data)) {

            $post_data['store_id'] = $this->store_id;
            $post_data['store_passwd'] = $this->store_pass;

            $load_sslc = $this->sendRequest($post_data);

            if ($load_sslc) {
                if (isset($this->sslc_data['status']) && $this->sslc_data['status'] == 'SUCCESS') {
                    if (isset($this->sslc_data['GatewayPageURL']) && $this->sslc_data['GatewayPageURL'] != '') {

                        return response()->json([
                            'result' => true,
                            'url' =>  $this->sslc_data['GatewayPageURL'],
                            'message' => 'Redirect Url is found'
                        ]);
                    } else {
                        return response()->json([
                            'result' => false,
                            'url' => '',
                            'message' => 'No redirect URL found!'
                        ]);
                    }
                } else {

                    return response()->json([
                        'result' => false,
                        'url' => '',
                        'message' => "Invalid Credential!",
                    ]);
                }
            } else {

                return response()->json([
                    'result' => false,
                    'url' => '',
                    'message' => "Connectivity Issue. Please contact your sslcommerz manager",
                ]);
            }
        } else {

            return response()->json([
                'result' => false,
                'url' => '',
                'message' => "Please provide a valid information list about transaction with transaction id, amount, success url, fail url, cancel url, store id and pass at least",
            ]);
        }
    }


    # SEND CURL REQUEST
    public function sendRequest($data)
    {


        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, "https://" . $this->sslcMode() . ".sslcommerz.com/gwprocess/v3/api.php");
        curl_setopt($handle, CURLOPT_POST, 1);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

        if (SSLCZ_IS_LOCAL_HOST) {
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        } else {
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2); // Its default value is now 2
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        }


        $content = curl_exec($handle);

        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        if ($code == 200 && !(curl_errno($handle))) {
            $sslcommerzResponse = $content;

            # PARSE THE JSON RESPONSE
            $this->sslc_data = json_decode($sslcommerzResponse, true);

            return $this;
        } else {
            $msg = "FAILED TO CONNECT WITH SSLCOMMERZ API";
            $this->error = $msg;
            return false;
        }
    }

    # SET SSLCOMMERZ PAYMENT MODE - LIVE OR TEST
    public function setSSLCommerzMode($test)
    {
        if ($test) {
            $this->sslc_mode = "sandbox";
        } else {
            $this->sslc_mode = "securepay";
        }
    }

    # VALIDATE SSLCOMMERZ TRANSACTION
    public function sslcommerz_validate($merchant_trans_id, $merchant_trans_amount, $merchant_trans_currency, $post_data)
    {
        # MERCHANT SYSTEM INFO
        if ($merchant_trans_id != "" && $merchant_trans_amount != 0) {

            # CALL THE FUNCTION TO CHECK THE RESUKT
            $post_data['store_id'] = $this->store_id;
            $post_data['store_pass'] = $this->store_pass;

            if ($this->SSLCOMMERZ_hash_varify($this->store_pass, $post_data)) {

                $val_id = urlencode((string) ($post_data['val_id'] ?? ''));
                $store_id = urlencode($this->store_id);
                $store_passwd = urlencode($this->store_pass);
                $requested_url = ("https://" . $this->sslcMode() . ".sslcommerz.com/validator/api/validationserverAPI.php" . "?val_id=" . $val_id . "&store_id=" . $store_id . "&store_passwd=" . $store_passwd . "&v=1&format=json");

                $response = Http::timeout(30)->get($requested_url);
                $result = $response->successful() ? json_decode($response->body()) : null;

                if (is_object($result)) {
                    $this->sslc_data = $result;

                    # TRANSACTION INFO (only what the checks below read: a missing field fails them rather than throwing)
                    $status = $result->status ?? null;
                    $tran_id = $result->tran_id ?? '';
                    $amount = (float) ($result->amount ?? 0);
                    $currency_type = $result->currency_type ?? '';
                    $currency_amount = (float) ($result->currency_amount ?? 0);

                    # GIVE SERVICE
                    if ($status == "VALID" || $status == "VALIDATED") {
                        if ($merchant_trans_currency == "BDT") {
                            if (trim($merchant_trans_id) == trim($tran_id) && (abs($merchant_trans_amount - $amount) < 1) && trim($merchant_trans_currency) == trim('BDT')) {
                                return true;
                            } else {
                                # DATA TEMPERED
                                $this->error = "Data has been tempered";
                                return false;
                            }
                        } else {
                            //echo "trim($merchant_trans_id) == trim($tran_id) && ( abs($merchant_trans_amount-$currency_amount) < 1 ) && trim($merchant_trans_currency)==trim($currency_type)";
                            if (trim($merchant_trans_id) == trim($tran_id) && (abs($merchant_trans_amount - $currency_amount) < 1) && trim($merchant_trans_currency) == trim($currency_type)) {
                                return true;
                            } else {
                                # DATA TEMPERED
                                $this->error = "Data has been tempered";
                                return false;
                            }
                        }
                    } else {
                        # FAILED TRANSACTION
                        $this->error = "Failed Transaction";
                        return false;
                    }
                } else {
                    # Failed to connect with SSLCOMMERZ
                    $this->error = "Faile to connect with SSLCOMMERZ";
                    return false;
                }
            } else {
                # Hash validation failed
                $this->error = "Hash validation failed";
                return false;
            }
        } else {
            # INVALID DATA
            $this->error = "Invalid data";
            return false;
        }
    }

    # FUNCTION TO CHECK HASH VALUE
    public function SSLCOMMERZ_hash_varify($store_passwd, $post_data)
    {

        if (isset($post_data) && isset($post_data['verify_sign']) && isset($post_data['verify_key'])) {
            # NEW ARRAY DECLARED TO TAKE VALUE OF ALL POST
            $pre_define_key = explode(',', $post_data['verify_key']);

            $new_data = [];
            if (!empty($pre_define_key)) {
                foreach ($pre_define_key as $value) {
                    if (isset($post_data[$value])) {
                        $new_data[$value] = ($post_data[$value]);
                    }
                }
            }
            # ADD MD5 OF STORE PASSWORD
            $new_data['store_passwd'] = md5($store_passwd);

            # SORT THE KEY AS BEFORE
            ksort($new_data);

            $hash_string = "";
            foreach ($new_data as $key => $value) {
                $hash_string .= $key . '=' . ($value) . '&';
            }
            $hash_string = rtrim($hash_string, '&');

            if (md5($hash_string) == $post_data['verify_sign']) {

                return true;
            } else {
                $this->error = "Verification signature not matched";
                return false;
            }
        } else {
            $this->error = 'Required data mission. ex: verify_key, verify_sign';
            return false;
        }
    }

    # FUNCTION TO GET IMAGES FROM WEB
    public function _get_image($gw = "", $source = [])
    {
        $logo = "";
        if (!empty($source) && isset($source['desc'])) {

            foreach ($source['desc'] as $key => $volume) {

                if (isset($volume['gw']) && $volume['gw'] == $gw) {

                    if (isset($volume['logo'])) {
                        $logo = str_replace("/gw/", "/gw1/", $volume['logo']);
                        break;
                    }
                }
            }
            return $logo;
        } else {
            return "";
        }
    }

    public function getResultData()
    {
        return $this->sslc_data;
    }
}
