<?php

namespace App\Services;

use Iyzipay\Model\Locale;
use Iyzipay\Model\PayWithIyzico;
use Iyzipay\Options;
use Iyzipay\Request\RetrievePayWithIyzicoRequest;

/**
 * The store's iyzico account, for the storefront's and the mobile API's
 * checkout: the credentials, and what iyzico's own server says about a payment.
 * Both callbacks used to credit whatever the browser or the app said was paid.
 */
class IyzicoPayments
{
    public function options(): Options
    {
        $options = new Options;
        $options->setApiKey((string) config('services.iyzico.api_key'));
        $options->setSecretKey((string) config('services.iyzico.secret_key'));
        $options->setBaseUrl(get_setting('iyzico_sandbox') == 1 ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com');

        return $options;
    }

    /**
     * The payment behind a checkout-form token, as iyzico reports it.
     *
     * @return array{paid: bool, paid_price: float, basket_id: string, raw: string|null}
     */
    public function retrieve(string $token): array
    {
        $request = new RetrievePayWithIyzicoRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId('123456789');
        $request->setToken($token);

        $payment = PayWithIyzico::retrieve($request, $this->options());

        return [
            'paid' => $payment->getStatus() === 'success' && $payment->getPaymentStatus() === 'SUCCESS',
            'paid_price' => (float) $payment->getPaidPrice(),
            'basket_id' => (string) $payment->getBasketId(),
            'raw' => $payment->getRawResult(),
        ];
    }
}
