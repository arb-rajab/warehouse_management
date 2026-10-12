<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CombinedOrder;
use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Services\GatewayPaymentService;
use App\Services\IyzicoPayments;
use App\Services\SessionGatewayPayment;
use App\Services\VerifiedPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\Locale;
use Iyzipay\Model\PaymentGroup;
use Iyzipay\Model\PayWithIyzicoInitialize;
use Iyzipay\Request\CreatePayWithIyzicoInitializeRequest;
use Illuminate\Support\Str;
use Redirect;
use Session;

class IyzicoController extends Controller
{
    public function index(Request $iyzicoRequest){

    }

    public function pay(){
        $options = app(IyzicoPayments::class)->options();

        if(Session::has('payment_type')){
            // callback() accepts only the payment iyzico reports for this basket.
            $basketId = Str::random(20);
            Session::put('iyzico_basket_id', $basketId);

            $iyzicoRequest = new CreatePayWithIyzicoInitializeRequest();
            $iyzicoRequest->setLocale(Locale::TR);
            $iyzicoRequest->setConversationId('123456789');

            $buyer = new Buyer();
            $buyer->setId("BY789");
            $buyer->setName("John");
            $buyer->setSurname("Doe");
            $buyer->setEmail(Auth::user()->email);
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

            if(Session::get('payment_type') == 'cart_payment'){
                $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));

                $iyzicoRequest->setPrice(round($combined_order->grand_total));
                $iyzicoRequest->setPaidPrice(round($combined_order->grand_total));
                $iyzicoRequest->setCurrency(config('services.iyzico.currency'));
                $iyzicoRequest->setBasketId($basketId);
                $iyzicoRequest->setPaymentGroup(PaymentGroup::SUBSCRIPTION);
                $iyzicoRequest->setCallbackUrl(route('iyzico.callback', ['payment_type' => Session::get('payment_type')]));

                $basketItems = [];
                $firstBasketItem = new BasketItem();
                $firstBasketItem->setId(rand(1000,9999));
                $firstBasketItem->setName("Cart Payment");
                $firstBasketItem->setCategory1("Accessories");
                $firstBasketItem->setItemType(BasketItemType::VIRTUAL);
                $firstBasketItem->setPrice(round($combined_order->grand_total));
                $basketItems[0] = $firstBasketItem;

                $iyzicoRequest->setBasketItems($basketItems);
            }

            if(Session::get('payment_type') == 'wallet_payment'){
                $iyzicoRequest->setPrice(round(Session::get('payment_data')['amount']));
                $iyzicoRequest->setPaidPrice(round(Session::get('payment_data')['amount']));
                $iyzicoRequest->setCurrency(config('services.iyzico.currency'));
                $iyzicoRequest->setBasketId($basketId);
                $iyzicoRequest->setPaymentGroup(PaymentGroup::SUBSCRIPTION);
                $iyzicoRequest->setCallbackUrl(route('iyzico.callback', ['payment_type' => Session::get('payment_type')]));

                $basketItems = [];
                $firstBasketItem = new BasketItem();
                $firstBasketItem->setId(rand(1000,9999));
                $firstBasketItem->setName("Wallet Payment");
                $firstBasketItem->setCategory1("Wallet");
                $firstBasketItem->setItemType(BasketItemType::VIRTUAL);
                $firstBasketItem->setPrice(round(Session::get('payment_data')['amount']));
                $basketItems[0] = $firstBasketItem;

                $iyzicoRequest->setBasketItems($basketItems);
            }

            if(Session::get('payment_type') == 'customer_package_payment'){
                $customer_package = CustomerPackage::findOrFail(Session::get('payment_data')['customer_package_id']);

                $iyzicoRequest->setPrice(round($customer_package->amount));
                $iyzicoRequest->setPaidPrice(round($customer_package->amount));
                $iyzicoRequest->setCurrency(config('services.iyzico.currency'));
                $iyzicoRequest->setBasketId($basketId);
                $iyzicoRequest->setPaymentGroup(PaymentGroup::SUBSCRIPTION);
                $iyzicoRequest->setCallbackUrl(route('iyzico.callback', ['payment_type' => Session::get('payment_type')]));

                $basketItems = [];
                $firstBasketItem = new BasketItem();
                $firstBasketItem->setId(rand(1000,9999));
                $firstBasketItem->setName("Package Payment");
                $firstBasketItem->setCategory1("Package");
                $firstBasketItem->setItemType(BasketItemType::VIRTUAL);
                $firstBasketItem->setPrice(round($customer_package->amount));
                $basketItems[0] = $firstBasketItem;

                $iyzicoRequest->setBasketItems($basketItems);
            }

            if(Session::get('payment_type') == 'seller_package_payment'){
                $seller_package = SellerPackage::findOrFail(Session::get('payment_data')['seller_package_id']);

                $iyzicoRequest->setPrice(round($seller_package->amount));
                $iyzicoRequest->setPaidPrice(round($seller_package->amount));
                $iyzicoRequest->setCurrency(config('services.iyzico.currency'));
                $iyzicoRequest->setBasketId($basketId);
                $iyzicoRequest->setPaymentGroup(PaymentGroup::SUBSCRIPTION);
                $iyzicoRequest->setCallbackUrl(route('iyzico.callback', ['payment_type' => Session::get('payment_type')]));

                $basketItems = [];
                $firstBasketItem = new BasketItem();
                $firstBasketItem->setId(rand(1000,9999));
                $firstBasketItem->setName("Package Payment");
                $firstBasketItem->setCategory1("Package");
                $firstBasketItem->setItemType(BasketItemType::VIRTUAL);
                $firstBasketItem->setPrice(round($seller_package->amount));
                $basketItems[0] = $firstBasketItem;

                $iyzicoRequest->setBasketItems($basketItems);
            }


            # make request
            $payWithIyzicoInitialize = PayWithIyzicoInitialize::create($iyzicoRequest, $options);

            # print result
            return Redirect::to($payWithIyzicoInitialize->getPayWithIyzicoPageUrl());
        }
        else {
            flash(translate('Opps! Something went wrong.'))->warning();
            return redirect()->route('cart');
        }
    }

    public function initPayment(Request $request){
		//bugs
    }

    /**
     * iyzico posts the checkout-form token back here. The callback URL used to
     * carry the payment type, the top-up amount, the order and the package, and
     * they were trusted. Now only the token is read: iyzico's server must report
     * that payment successful, for the basket this session started and for at
     * least the amount due (read from the database), and a token completes one
     * payment only. The URL's other segments are ignored.
     */
    public function callback(Request $request, $payment_type = null){
        $token = (string) $request->input('token');
        $basketId = (string) Session::get('iyzico_basket_id');
        $amountDue = app(SessionGatewayPayment::class)->amountDue();

        if ($token === '' || $basketId === '' || $amountDue === null) {
            return app(SessionGatewayPayment::class)->notVerified();
        }

        $payment = app(IyzicoPayments::class)->retrieve($token);

        if (! $payment['paid'] || ! hash_equals($basketId, $payment['basket_id']) || ! GatewayPaymentService::covers($payment['paid_price'], $amountDue)) {
            return app(SessionGatewayPayment::class)->notVerified();
        }

        Session::forget('iyzico_basket_id');

        return app(SessionGatewayPayment::class)->complete(new VerifiedPayment('iyzico', $token, $payment['paid_price'], $payment['raw']));
    }
}
