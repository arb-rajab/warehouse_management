<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\GatewayCurrency;
use App\Services\GatewayReturn;
use App\Services\IyzicoPayments;
use App\Services\SessionGatewayPayment;
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
     * refused with a message and nothing is recorded. Otherwise the payment is recorded as
     * pending under the checkout-form token iyzico issued for it (GatewayReturn), the one value
     * callback() reads from the return.
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
        $payment = app(SessionGatewayPayment::class)->pending();
        $currency = GatewayCurrency::forGateway('iyzico');
        $converted = $payment === null ? null : $currency->fromStore($payment['amount']);
        $payer = Auth::user();

        if ($payment === null || ! $iyzico->supports($paymentType) || $converted === null || $payer === null) {
            Log::error('iyzico refused: nothing due, or the store has no active rate for the gateway currency.', ['type' => $paymentType, 'currency' => $currency->chargeCode()]);

            return $this->failed();
        }

        $page = $iyzico->initialize($iyzico->buildInitializeRequest(
            $payer, $paymentType, $converted, $currency->chargeCode(),
            route('iyzico.callback', ['payment_type' => $paymentType]), Str::random(20), (string) request()->ip(),
        ));

        if ($page === null) {
            return $this->failed();
        }

        app(GatewayReturn::class)->begin('iyzico', $page['token'], $payment, $converted, $currency->chargeCode());

        return Redirect::to($page['url']);
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
     * iyzico posts the checkout-form token back here, cross-site, so the post carries no
     * session (this route runs without one: routes/shop-gateway-returns.php). The callback URL
     * used to carry the payment type, the top-up amount, the order and the package, and they
     * were trusted; its segments are ignored. Only the token is read: it must be one pay()
     * recorded as pending, iyzico's server must report that payment successful, and the paid
     * price and currency must be the recorded converted amount to the cent. A token completes
     * one payment, credited to the recorded payer.
     */
    public function callback(Request $request, $payment_type = null){
        $returns = app(GatewayReturn::class);
        $token = (string) $request->input('token');
        $pending = $returns->find('iyzico', $token);

        if (is_string($pending)) {
            return $returns->refuse($pending, 'iyzico', $token);
        }

        if ($pending->completed_at !== null) {
            return $returns->finished($pending);
        }

        $payment = app(IyzicoPayments::class)->retrieve($token);

        if (! $payment['paid']) {
            return $returns->refuse('not_verified', 'iyzico', $token, $pending);
        }

        return $returns->complete($pending, $payment['paid_price'], $payment['currency'], $payment['raw']);
    }
}
