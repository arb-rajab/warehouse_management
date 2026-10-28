<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Services\GatewayPaymentService;
use App\Services\SessionGatewayPayment;
use App\Services\VerifiedPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Session;

class VoguepayController extends Controller
{
    public function pay()
    {
        // The reference the payment form sends and paymentSuccess() checks.
        Session::put('voguepay_merchant_ref', Str::random(20));

        if (Session::get('payment_type') == 'cart_payment') {
            return view('shop.frontend.voguepay.cart_payment_vogue');
        }
        elseif (Session::get('payment_type') == 'wallet_payment') {
            return view('shop.frontend.voguepay.wallet_payment_vogue');
        }
        elseif (Session::get('payment_type') == 'customer_package_payment') {
            return view('shop.frontend.voguepay.customer_package_payment_vogue');
        }
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
        $isApproved = is_array($transaction)
            && ($transaction['response_message'] ?? null) === 'Approved'
            && hash_equals((string) $id, (string) ($transaction['transaction_id'] ?? ''))
            && hash_equals($merchantRef, (string) ($transaction['merchant_ref'] ?? ''))
            && GatewayPaymentService::covers((float) ($transaction['total'] ?? 0), $amountDue);

        if (! $isApproved) {
            flash(__('storefront.checkout.payment_failed'))->error();
            return redirect()->route('home');
        }

        Session::forget('voguepay_merchant_ref');

        return app(SessionGatewayPayment::class)->complete(new VerifiedPayment('voguepay', (string) $id, (float) $transaction['total'], json_encode($transaction)));
    }

    public function paymentFailure($id)
    {
        flash(__('storefront.checkout.payment_failed'))->error();
        return redirect()->route('home');
    }
}
