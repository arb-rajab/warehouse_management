<?php


namespace App\Http\Controllers\Api\V1;


use App\Models\StoreUser;
use App\Services\ApiGatewayPayment;
use App\Services\GatewayCurrency;
use App\Services\GatewayPaymentService;
use App\Services\IyzicoPayments;
use App\Services\VerifiedPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

        $iyzico = app(IyzicoPayments::class);

        if ($iyzico->identityNumber() === '') {
            Log::error('iyzico refused: IYZICO_IDENTITY_NUMBER is not set in .env.');

            return response()->json(['result' => false, 'message' => __('storefront.checkout.iyzico_unavailable')]);
        }

        $currency = GatewayCurrency::forGateway('iyzico');
        $converted = $currency->fromStore((float) $payment['amount']);
        $payer = StoreUser::query()->find($payment['user_id']);

        if (! $iyzico->supports($payment['payment_type']) || $converted === null || $payer === null) {
            Log::error('iyzico refused: the store has no active rate for the gateway currency.', ['type' => $payment['payment_type'], 'currency' => $currency->chargeCode()]);

            return response()->json(['result' => false, 'message' => __('storefront.checkout.payment_failed')]);
        }

        $url = $iyzico->initialize($iyzico->buildInitializeRequest(
            $payer, $payment['payment_type'], $converted, $currency->chargeCode(),
            route('api.iyzico.callback'), Str::random(20), (string) $request->ip(),
        ));

        if ($url === null) {
            return response()->json(['result' => false, 'message' => __('storefront.checkout.payment_failed')]);
        }

        return Redirect::to($url);
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
        $currency = GatewayCurrency::forGateway('iyzico');
        $verified = new VerifiedPayment('iyzico', $token, $currency->paidInStoreCurrency($retrieved['paid_price'], (float) $payment['amount']), $retrieved['raw']);

        // Paid, in the currency this store charges, for the amount due: exactly in the store's own
        // currency, else the converted amount to the cent.
        if (! $retrieved['paid'] || strtoupper((string) ($retrieved['currency'] ?? '')) !== $currency->chargeCode()
            || ! ($currency->isStoreCurrency() ? GatewayPaymentService::covers($retrieved['paid_price'], $payment['amount']) : $currency->matchesDue($retrieved['paid_price'], (float) $payment['amount']))
            || ! app(GatewayPaymentService::class)->complete($verified, $payment['user_id'], $payment['payment_type'], $payment['combined_order_id'], $payment['package_id'])) {
            return response()->json(['result' => false, 'message' => __('storefront.checkout.payment_could_not_be_verified')]);
        }

        return response()->json(['result' => true, 'message' => __('api.payment.successful')]);
    }
}
