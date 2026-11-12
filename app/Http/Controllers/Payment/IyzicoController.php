<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\GatewayCurrency;
use App\Services\GatewayPaymentService;
use App\Services\IyzicoPayments;
use App\Services\SessionGatewayPayment;
use App\Services\VerifiedPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Redirect;
use Session;

class IyzicoController extends Controller
{
    public function index(Request $iyzicoRequest){

    }

    /**
     * Start the payment this session is making. The amount due comes from the database (an
     * order of the signed-in customer, a package's price, the top-up asked for), is converted to
     * iyzico's currency and sent to the cent, and the buyer is the customer's own details. A
     * currency the store has no active rate for, or an iyzico that gives no payment page, is
     * refused with a message and nothing is recorded.
     */
    public function pay()
    {
        if (! Session::has('payment_type')) {
            flash(__('storefront.common.opps_something_went_wrong'))->warning();

            return redirect()->route('cart');
        }

        $iyzico = app(IyzicoPayments::class);

        if ($iyzico->identityNumber() === '') {
            Log::error('iyzico refused: IYZICO_IDENTITY_NUMBER is not set in .env.');
            flash(__('storefront.checkout.iyzico_unavailable'))->error();

            return redirect()->route('cart');
        }

        $paymentType = (string) Session::get('payment_type');
        $due = app(SessionGatewayPayment::class)->amountDue();
        $currency = GatewayCurrency::forGateway('iyzico');
        $converted = $due === null ? null : $currency->fromStore($due);
        $payer = Auth::user();

        if (! $iyzico->supports($paymentType) || $converted === null || $payer === null) {
            Log::error('iyzico refused: nothing due, or the store has no active rate for the gateway currency.', ['type' => $paymentType, 'currency' => $currency->chargeCode()]);

            return $this->failed();
        }

        // callback() accepts only the payment iyzico reports for this basket.
        $basketId = Str::random(20);
        Session::put('iyzico_basket_id', $basketId);

        $url = $iyzico->initialize($iyzico->buildInitializeRequest(
            $payer, $paymentType, $converted, $currency->chargeCode(),
            route('iyzico.callback', ['payment_type' => $paymentType]), $basketId, (string) request()->ip(),
        ));

        if ($url === null) {
            Session::forget('iyzico_basket_id');

            return $this->failed();
        }

        return Redirect::to($url);
    }

    private function failed()
    {
        flash(__('storefront.checkout.payment_failed'))->error();

        return redirect()->route('cart');
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
        $currency = GatewayCurrency::forGateway('iyzico');

        // Paid, for this basket, in the currency this store charges, and for the amount due:
        // exactly in the store's own currency, else the converted amount to the cent.
        if (! $payment['paid'] || ! hash_equals($basketId, $payment['basket_id'])
            || strtoupper((string) ($payment['currency'] ?? '')) !== $currency->chargeCode()
            || ! ($currency->isStoreCurrency() ? GatewayPaymentService::covers($payment['paid_price'], $amountDue) : $currency->matchesDue($payment['paid_price'], $amountDue))) {
            return app(SessionGatewayPayment::class)->notVerified();
        }

        Session::forget('iyzico_basket_id');

        return app(SessionGatewayPayment::class)->complete(new VerifiedPayment('iyzico', $token, $currency->paidInStoreCurrency($payment['paid_price'], $amountDue), $payment['raw']));
    }
}
