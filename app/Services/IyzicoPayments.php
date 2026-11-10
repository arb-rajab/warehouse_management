<?php

namespace App\Services;

use App\Models\StoreUser;
use Illuminate\Support\Facades\Log;
use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\Locale;
use Iyzipay\Model\PaymentGroup;
use Iyzipay\Model\PayWithIyzico;
use Iyzipay\Model\PayWithIyzicoInitialize;
use Iyzipay\Options;
use Iyzipay\Request\CreatePayWithIyzicoInitializeRequest;
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

    /** What a payment of each type is called in the basket: [name, category]. */
    private const ITEMS = [
        GatewayPaymentService::CART => ['Cart Payment', 'Accessories'],
        GatewayPaymentService::WALLET => ['Wallet Payment', 'Wallet'],
        GatewayPaymentService::CUSTOMER_PACKAGE => ['Customer Package Payment', 'CustomerPackage'],
        GatewayPaymentService::SELLER_PACKAGE => ['Seller Package Payment', 'SellerPackage'],
    ];

    /** Turkish for a Turkish storefront, English otherwise. */
    public function locale(): string
    {
        return app()->getLocale() === 'tr' ? Locale::TR : Locale::EN;
    }

    /** Whether a payment of `$paymentType` can be started. */
    public function supports(string $paymentType): bool
    {
        return isset(self::ITEMS[$paymentType]);
    }

    /**
     * The checkout-form request for `$payer` paying `$amount` (already in `$currencyCode`, to the
     * cent, never rounded to a whole unit). The buyer and both addresses are the customer's own
     * details, not a placeholder person.
     */
    public function buildInitializeRequest(StoreUser $payer, string $paymentType, float $amount, string $currencyCode, string $callbackUrl, string $basketId, string $ip): CreatePayWithIyzicoInitializeRequest
    {
        [$itemName, $itemCategory] = self::ITEMS[$paymentType];
        $price = GatewayCurrency::format($amount);

        $name = trim((string) $payer->name) !== '' ? trim((string) $payer->name) : (string) $payer->email;
        $parts = preg_split('/\s+/', $name, 2) ?: [$name];
        $address = trim((string) $payer->address) !== '' ? (string) $payer->address : '-';
        $city = trim((string) $payer->city) !== '' ? (string) $payer->city : '-';
        $country = trim((string) $payer->country) !== '' ? (string) $payer->country : '-';

        $request = new CreatePayWithIyzicoInitializeRequest;
        $request->setLocale($this->locale());
        $request->setConversationId($basketId);

        $buyer = new Buyer;
        $buyer->setId((string) $payer->id);
        $buyer->setName($parts[0]);
        $buyer->setSurname($parts[1] ?? $parts[0]);
        $buyer->setEmail((string) $payer->email);
        if (trim((string) $payer->phone) !== '') {
            $buyer->setGsmNumber((string) $payer->phone);
        }
        $buyer->setIdentityNumber((string) config('services.iyzico.identity_number'));
        $buyer->setRegistrationAddress($address);
        $buyer->setCity($city);
        $buyer->setCountry($country);
        if (trim((string) $payer->postal_code) !== '') {
            $buyer->setZipCode((string) $payer->postal_code);
        }
        $buyer->setIp($ip);
        $request->setBuyer($buyer);

        foreach (['setShippingAddress', 'setBillingAddress'] as $setter) {
            $location = new Address;
            $location->setContactName($name);
            $location->setCity($city);
            $location->setCountry($country);
            $location->setAddress($address);
            if (trim((string) $payer->postal_code) !== '') {
                $location->setZipCode((string) $payer->postal_code);
            }
            $request->$setter($location);
        }

        $request->setPrice($price);
        $request->setPaidPrice($price);
        $request->setCurrency($currencyCode);
        $request->setBasketId($basketId);
        $request->setPaymentGroup(PaymentGroup::SUBSCRIPTION);
        $request->setCallbackUrl($callbackUrl);

        $item = new BasketItem;
        $item->setId($basketId);
        $item->setName($itemName);
        $item->setCategory1($itemCategory);
        $item->setItemType(BasketItemType::VIRTUAL);
        $item->setPrice($price);
        $request->setBasketItems([$item]);

        return $request;
    }

    /** iyzico's payment page for `$request`, or null (logged) when iyzico gives none. */
    public function initialize(CreatePayWithIyzicoInitializeRequest $request): ?string
    {
        $result = PayWithIyzicoInitialize::create($request, $this->options());
        $url = $result->getPayWithIyzicoPageUrl();

        if (! is_string($url) || $url === '') {
            Log::error('iyzico gave no payment page.', ['status' => $result->getStatus(), 'error' => $result->getErrorMessage(), 'code' => $result->getErrorCode()]);

            return null;
        }

        return $url;
    }

    /**
     * The payment behind a checkout-form token, as iyzico reports it.
     *
     * @return array{paid: bool, paid_price: float, currency: string, basket_id: string, raw: string|null}
     */
    public function retrieve(string $token): array
    {
        $request = new RetrievePayWithIyzicoRequest;
        $request->setLocale($this->locale());
        $request->setConversationId('123456789');
        $request->setToken($token);

        $payment = PayWithIyzico::retrieve($request, $this->options());

        return [
            'paid' => $payment->getStatus() === 'success' && $payment->getPaymentStatus() === 'SUCCESS',
            'paid_price' => (float) $payment->getPaidPrice(),
            'currency' => strtoupper((string) $payment->getCurrency()),
            'basket_id' => (string) $payment->getBasketId(),
            'raw' => $payment->getRawResult(),
        ];
    }
}
