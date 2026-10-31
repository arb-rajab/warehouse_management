<?php


namespace App\Http\Controllers\Api\V2;


use App\Services\ApiGatewayPayment;
use App\Services\GatewayPaymentService;
use App\Services\IyzicoPayments;
use App\Services\VerifiedPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\Currency;
use Iyzipay\Model\Locale;
use Iyzipay\Model\PaymentGroup;
use Iyzipay\Model\PayWithIyzicoInitialize;
use Iyzipay\Request\CreatePayWithIyzicoInitializeRequest;
use Redirect;

class IyzicoController extends Controller
{
    /**
     * The payer is the signed-in customer and the price their own order's total
     * or the package's price, from the database; the request's `user_id` and
     * (except for a top-up) `amount` are not read.
     */
    public function init(Request $request)
    {
        $payment = app(ApiGatewayPayment::class)->resolve($request);

        if ($payment === null) {
            return response()->json(['result' => false, 'message' => __('api.payment.nothing_due')]);
        }

        $iyzicoRequest = new CreatePayWithIyzicoInitializeRequest();
        $iyzicoRequest->setLocale(Locale::TR);
        $iyzicoRequest->setConversationId('123456789');

        $buyer = new Buyer();
        $buyer->setId("BY789");
        $buyer->setName("John");
        $buyer->setSurname("Doe");
        $buyer->setEmail("email@email.com");
        $buyer->setIdentityNumber("74300864791");
        $buyer->setRegistrationAddress("Nidakule Göztepe, Merdivenköy Mah. Bora Sok. No:1");
        $buyer->setCity("Istanbul");
        $buyer->setCountry("Turkey");
        $iyzicoRequest->setBuyer($buyer);
        $shippingAddress = new Address();
        $shippingAddress->setContactName("Jane Doe");
        $shippingAddress->setCity("Istanbul");
        $shippingAddress->setCountry("Turkey");
        $shippingAddress->setAddress("Nidakule Göztepe, Merdivenköy Mah. Bora Sok. No:1");
        $iyzicoRequest->setShippingAddress($shippingAddress);

        $billingAddress = new Address();
        $billingAddress->setContactName("Jane Doe");
        $billingAddress->setCity("Istanbul");
        $billingAddress->setCountry("Turkey");
        $billingAddress->setAddress("Nidakule Göztepe, Merdivenköy Mah. Bora Sok. No:1");
        $iyzicoRequest->setBillingAddress($billingAddress);

        [$itemName, $itemCategory] = match ($payment['payment_type']) {
            GatewayPaymentService::CART => ['Cart Payment', 'Accessories'],
            GatewayPaymentService::WALLET => ['Wallet Payment', 'Wallet'],
            GatewayPaymentService::SELLER_PACKAGE => ['Seller Package Payment', 'SellerPackage'],
            default => ['Customer Package Payment', 'CustomerPackage'],
        };

        $iyzicoRequest->setPrice(round($payment['amount']));
        $iyzicoRequest->setPaidPrice(round($payment['amount']));
        $iyzicoRequest->setCurrency(Currency::TL);
        $iyzicoRequest->setBasketId(Str::random(20));
        $iyzicoRequest->setPaymentGroup(PaymentGroup::SUBSCRIPTION);
        $iyzicoRequest->setCallbackUrl(route('api.iyzico.callback'));

        $firstBasketItem = new BasketItem();
        $firstBasketItem->setId(rand(1000, 9999));
        $firstBasketItem->setName($itemName);
        $firstBasketItem->setCategory1($itemCategory);
        $firstBasketItem->setItemType(BasketItemType::VIRTUAL);
        $firstBasketItem->setPrice(round($payment['amount']));
        $iyzicoRequest->setBasketItems([$firstBasketItem]);

        $payWithIyzicoInitialize = PayWithIyzicoInitialize::create($iyzicoRequest, app(IyzicoPayments::class)->options());

        # print result
        return Redirect::to($payWithIyzicoInitialize->getPayWithIyzicoPageUrl());
    }

    /**
     * iyzico posts the token back here; this only reports what iyzico's server
     * says about it, and credits nothing.
     */
    public function callback(Request $request)
    {
        $payment = app(IyzicoPayments::class)->retrieve((string) $request->input('token'));

        if ($payment['paid']) {
            return response()->json(['result' => true, 'message' => __('api.payment.successful'), 'payment_details' => $payment['raw']]);
        } else {
            return response()->json(['result' => false, 'message' => __('storefront.checkout.payment_failed'), 'payment_details' => $payment['raw']]);
        }
    }

    /**
     * The app reports a payment here. The report used to be credited as it came:
     * the order, the account, the package and the amount, with no sign-in. Now
     * the payment is looked up on iyzico's server by its token (the `token`
     * field, or the one in the `payment_details` the callback returned), must be
     * successful and pay what the signed-in customer owes (from the database),
     * and a token is credited once.
     */
    public function payment_success(Request $request)
    {
        $payment = app(ApiGatewayPayment::class)->resolve($request);
        $details = json_decode((string) $request->input('payment_details'), true);
        $token = (string) ($request->input('token') ?: (is_array($details) ? ($details['token'] ?? '') : ''));

        if ($payment === null || $token === '') {
            return response()->json(['result' => false, 'message' => __('storefront.checkout.payment_could_not_be_verified')]);
        }

        $retrieved = app(IyzicoPayments::class)->retrieve($token);
        $verified = new VerifiedPayment('iyzico', $token, $retrieved['paid_price'], $retrieved['raw']);

        if (! $retrieved['paid'] || ! GatewayPaymentService::covers($retrieved['paid_price'], $payment['amount'])
            || ! app(GatewayPaymentService::class)->complete($verified, $payment['user_id'], $payment['payment_type'], $payment['combined_order_id'], $payment['package_id'])) {
            return response()->json(['result' => false, 'message' => __('storefront.checkout.payment_could_not_be_verified')]);
        }

        return response()->json(['result' => true, 'message' => __('api.payment.successful')]);
    }
}
