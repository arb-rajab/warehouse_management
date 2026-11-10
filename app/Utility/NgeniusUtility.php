<?php

namespace App\Utility;

use Illuminate\Support\Facades\Http;

class NgeniusUtility
{
    /** The identity or gateway URL of the configured environment (`NGENIUS_MODE`: uat, the default, or live). */
    public static function getUrl($key)
    {
        return (string) config('services.ngenius.urls.'.self::getMode().'.'.$key);
    }

    /** 'live' only when NGENIUS_MODE says so; everything else is N-Genius's UAT test environment. */
    public static function getMode()
    {
        return strtolower((string) config('services.ngenius.mode')) === 'live' ? 'live' : 'uat';
    }

    public static function getAccessToken()
    {
        $apikey = config('services.ngenius.api_key'); // set your service account API key (example only)
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


    /**
     * Create the order at N-Genius and send the customer to its payment page.
     *
     * `$amount` is in minor units (1000 = 10.00) of `$currencyCode`. Returns the redirect to
     * N-Genius's payment page, or null when N-Genius gave no payment link (it was unreachable,
     * refused the credentials or the order): the caller tells the customer, nothing is recorded.
     */
    public static function make_payment($callback_url, $payment_type, $amount, string $currencyCode = '')
    {
        $order = new \stdClass();
        $order->action = 'SALE'; // authorize and capture in one step
        $order->amount = new \stdClass();
        $order->amount->currencyCode = $currencyCode !== '' ? $currencyCode : strtoupper((string) config('services.ngenius.currency'));
        $order->amount->value = $amount; // minor units (1000 = 10.00)
        $order->language = 'en'; // payment page language ('en' or 'ar' only)
        $order->merchantOrderReference = time();
        $order->merchantAttributes = new \stdClass();
        $order->merchantAttributes->redirectUrl = $callback_url; // where N-Genius returns the customer to
        $order = json_encode($order);

        $outletRef = config('services.ngenius.outlet_id');
        $txnServiceURL = self::getUrl('gateway')."/transactions/outlets/$outletRef/orders";
        $access_token = self::getAccessToken();

        $orderCreateHeaders = ['Authorization: Bearer '.$access_token, 'Content-Type: application/vnd.ni-payment.v2+json', 'Accept: application/vnd.ni-payment.v2+json'];
        $orderCreateResponse = json_decode((string) self::invokeCurlRequest('POST', $txnServiceURL, $orderCreateHeaders, $order));

        $paymentLink = $orderCreateResponse->_links->payment->href ?? null; // the payment page to send the customer to
        $orderReference = $orderCreateResponse->reference ?? null; // the order, kept so the callback accepts only it

        if (! is_string($paymentLink) || $paymentLink === '' || ! is_string($orderReference) || $orderReference === '') {
            return null;
        }

        // The only order the callback accepts for this session.
        session()->put('ngenius_order_ref', $orderReference);
        session()->save();

        return redirect()->away($paymentLink);
    }

    /**
     * The order as N-Genius's own server reports it, or null when it cannot be
     * read.
     *
     * @return array<string, mixed>|null
     */
    public static function retrieve_order($orderRef)
    {
        $outletRef = config('services.ngenius.outlet_id'); // set your outlet reference/ID value here (example only)
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
