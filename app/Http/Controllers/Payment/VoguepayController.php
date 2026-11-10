<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Services\GatewayCurrency;
use App\Services\GatewayPaymentService;
use App\Services\SessionGatewayPayment;
use App\Services\VerifiedPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Session;

class VoguepayController extends Controller
{
    /**
     * Show VoguePay's payment form for what this session is paying: cart, wallet top-up or
     * customer package. The total is the amount due from the database (with the payer's
     * ownership check), converted to VoguePay's currency and kept to the cent, and the customer
     * is the signed-in customer. A currency the store has no active rate for is refused.
     */
    public function pay()
    {
        $paymentType = (string) Session::get('payment_type');
        $due = app(SessionGatewayPayment::class)->amountDue();
        $currency = GatewayCurrency::forGateway('voguepay');
        $converted = $due === null ? null : $currency->fromStore($due);
        $payer = Auth::user();

        if (! in_array($paymentType, [GatewayPaymentService::CART, GatewayPaymentService::WALLET, GatewayPaymentService::CUSTOMER_PACKAGE], true) || $converted === null || $payer === null) {
            Log::error('VoguePay refused: nothing due, or the store has no active rate for the gateway currency.', ['type' => $paymentType, 'currency' => $currency->chargeCode()]);
            flash(__('storefront.checkout.payment_failed'))->error();

            return redirect()->route('cart');
        }

        // The reference the payment form sends and paymentSuccess() checks.
        $merchantRef = Str::random(20);
        Session::put('voguepay_merchant_ref', $merchantRef);

        return view('shop.frontend.voguepay.pay', [
            'merchantId' => get_setting('voguepay_sandbox') == 1 ? 'demo' : config('services.voguepay.merchant_id'),
            'total' => GatewayCurrency::format($converted),
            'currency' => $currency->chargeCode(),
            'merchantRef' => $merchantRef,
            'payer' => $payer,
            'successUrl' => route('voguepay.success', ['id' => '__id__']),
            'failureUrl' => route('voguepay.failure', ['id' => '__id__']),
            'homeUrl' => route('home'),
        ]);
    }

    /**
     * VoguePay sends the customer to /vogue-pay/success/<transaction id>, and any
     * approved transaction used to pay whatever this session was paying for. The
     * transaction, as VoguePay's server reports it, must carry the reference this
     * session's payment form sent and pay at least the amount due, and a
     * transaction completes one payment only.
     */
    public function paymentSuccess($id)
    {
        $merchantRef = (string) Session::get('voguepay_merchant_ref');
        $amountDue = app(SessionGatewayPayment::class)->amountDue();

        if ($merchantRef === '' || $amountDue === null) {
            return app(SessionGatewayPayment::class)->notVerified();
        }

        $query = ['v_transaction_id' => (string) $id, 'type' => 'json'];
        if (BusinessSetting::where('type', 'voguepay_sandbox')->first()?->value == 1) {
            $query['demo'] = 'true';
        }

        $transaction = Http::timeout(30)->get('https://voguepay.com/', $query)->json();
        $currency = GatewayCurrency::forGateway('voguepay');
        $reported = is_array($transaction) ? (float) ($transaction['total'] ?? 0) : 0.0;
        $isApproved = is_array($transaction)
            && ($transaction['response_message'] ?? null) === 'Approved'
            && hash_equals((string) $id, (string) ($transaction['transaction_id'] ?? ''))
            && hash_equals($merchantRef, (string) ($transaction['merchant_ref'] ?? ''))
            && ($currency->isStoreCurrency() ? GatewayPaymentService::covers($reported, $amountDue) : $currency->matchesDue($reported, $amountDue));

        if (! $isApproved) {
            flash(__('storefront.checkout.payment_failed'))->error();
            return redirect()->route('home');
        }

        Session::forget('voguepay_merchant_ref');

        return app(SessionGatewayPayment::class)->complete(new VerifiedPayment('voguepay', (string) $id, $currency->paidInStoreCurrency($reported, $amountDue), json_encode($transaction)));
    }

    public function paymentFailure($id)
    {
        flash(__('storefront.checkout.payment_failed'))->error();
        return redirect()->route('home');
    }
}
