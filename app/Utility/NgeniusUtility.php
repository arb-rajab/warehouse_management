<?php

namespace App\Utility;

use Illuminate\Support\Facades\Http;

class NgeniusUtility
{
    public static function getUrl($key)
    {
        $mode = self::getMode();
        $url['sandbox']['identity'] = "https://api-gateway.sandbox.ngenius-payments.com/identity/auth/access-token";
        $url['sandbox']['gateway'] = "https://api-gateway.sandbox.ngenius-payments.com";
        // sandbox urls do not work as the identity is not retrived by sandbox identity

        $url['real']['identity'] = "https://identity-uat.ngenius-payments.com/auth/realms/ni/protocol/openid-connect/token";
        $url['real']['gateway'] = "https://api-gateway-uat.ngenius-payments.com";

        return $url[$mode][$key];
    }

    //sandbox or real
    public static function getMode()
    {
        $sandbox = false; //check from db or env
        // sandbox urls do not work as the identity is not retrived by sandbox identity

        return $sandbox ? "sandbox" : "real";
    }

    public static function getAccessToken()
    {
        $apikey = env("NGENIUS_API_KEY"); // set your service account API key (example only)
        $idServiceURL = self::getUrl('identity');                   // set the identity service URL (example only)

        $tokenHeaders = ["Authorization: Basic $apikey", "Content-Type: application/x-www-form-urlencoded"];
        $tokenResponse = self::invokeCurlRequest("POST", $idServiceURL, $tokenHeaders, http_build_query(['grant_type' => 'client_credentials']));

//        $tokenHeaders = array("Authorization: Basic $apikey", "Content-Type: application/vnd.ni-payment.v2+json", "Accept: application/vnd.ni-payment.v2+json");
//        $tokenResponse = self::invokeCurlRequest("POST", $idServiceURL, $tokenHeaders, null);


        $tokenResponse = json_decode((string) $tokenResponse);
        //dd($tokenResponse);
        $access_token = $tokenResponse->access_token ?? '';


        return $access_token;
    }


    public static function make_payment($callback_url, $payment_type, $amount)
    {
        $order = new \StdClass();

        $order->action = "SALE";                                        // Transaction mode ("AUTH" = authorize only, no automatic settle/capture, "SALE" = authorize + automatic settle/capture)
        $order->amount = new \stdClass();
        $order->amount->currencyCode = env('NGENIUS_CURRENCY', "AED");                           // Payment currency ('AED' only for now)
        $order->amount->value = $amount;                                   // Minor units (1000 = 10.00 AED)
        $order->language = "en";                                        // Payment page language ('en' or 'ar' only)
        $order->merchantOrderReference = time();                                        // Payment page language ('en' or 'ar' only)
        //$order->merchantAttributes->redirectUrl = "http://premizer.com/test/pp.php";     // A redirect URL to a page on your site to return the customer to
        //$order->merchantAttributes->redirectUrl = "http://192.168.0.111:8080/n-genius/pp.php";     // A redirect URL to a page on your site to return the customer to
        $order->merchantAttributes = new \stdClass();
        $order->merchantAttributes->redirectUrl = $callback_url;
        // A redirect URL to a page on your site to return the customer to
        //$order->merchantAttributes->redirectUrl = "http:// 192.168.0.111:8080/n-genius/pp.php";     // A redirect URL to a page on your site to return the customer to
        $order = json_encode($order);


        $outletRef = env("NGENIUS_OUTLET_ID"); // set your outlet reference/ID value here (example only)
        $txnServiceURL = self::getUrl('gateway') . "/transactions/outlets/$outletRef/orders";             // set the transaction service URL (example only)
        $access_token = self::getAccessToken();

        $orderCreateHeaders = ["Authorization: Bearer " . $access_token, "Content-Type: application/vnd.ni-payment.v2+json", "Accept: application/vnd.ni-payment.v2+json"];
        $orderCreateResponse = self::invokeCurlRequest("POST", $txnServiceURL, $orderCreateHeaders, $order);

        $orderCreateResponse = json_decode($orderCreateResponse);

        //dd($callback_url,$orderCreateResponse);

        $paymentLink = $orderCreateResponse->_links->payment->href;     // the link to the payment page for redirection (either full-page redirect or iframe)
        $orderReference = $orderCreateResponse->reference;              // the reference to the order, which you should store in your records for future interaction with this order

        // The only order the callback accepts for this session.
        session()->put('ngenius_order_ref', $orderReference);
        session()->save();

        /*echo "<!DOCTYPE html>
        <html>
        <script type=\"text/javascript\">
        window.location = \"$paymentLink\";

        </script>
        </html>";*/

        header("Location: " . $paymentLink);                     // execute redirect
        exit;


    }

    /**
     * The order as N-Genius's own server reports it, or null when it cannot be
     * read.
     *
     * @return array<string, mixed>|null
     */
    public static function retrieve_order($orderRef)
    {
        $outletRef = env("NGENIUS_OUTLET_ID"); // set your outlet reference/ID value here (example only)
        $orderCheckURL = self::getUrl('gateway') . "/transactions/outlets/$outletRef/orders/" . rawurlencode($orderRef);
        $access_token = self::getAccessToken();

        $headers = ["Authorization: Bearer " . $access_token];
        $orderStatusResponse = json_decode((string) self::invokeCurlRequest("GET", $orderCheckURL, $headers, null), true);

        return is_array($orderStatusResponse) ? $orderStatusResponse : null;
    }

    public static function invokeCurlRequest($type, $url, $headers, $post)
    {
        $headerMap = [];
        foreach ($headers as $header) {
            [$name, $value] = array_map('trim', explode(':', $header, 2));
            $headerMap[$name] = $value;
        }

        $request = Http::timeout(30)->withHeaders($headerMap);

        if ($type == "POST") {
            return $request->withBody((string) $post, $headerMap['Content-Type'] ?? 'application/json')->post($url)->body();
        }

        return $request->get($url)->body();
    }

    public static function initPayment(){
        return redirect()->route('home');
    }
}
