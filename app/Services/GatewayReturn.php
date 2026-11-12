<?php

namespace App\Services;

use App\Models\GatewayPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * The storefront gateways that send the customer back with a cross-site POST
 * (SSLCommerz, AamarPay, iyzico). With SESSION_SAME_SITE=lax that POST carries
 * no session cookie, so the payment could not be found in the session and was
 * credited to nobody; and answering it with a fresh session overwrote the
 * customer's cookie and signed them out.
 *
 * Now the payment is recorded at the start, server side, under the reference
 * sent to the gateway (begin()), and the return is authenticated by that
 * reference plus the gateway's own server-side verification, never by the
 * session: the return routes run without the session (routes/shop-gateway-returns.php).
 * The owner, what is paid for and the amounts are the recorded row's; the
 * request gives only the reference. The browser then gets a 303 to a GET in the
 * `web` group, which carries the lax cookie and lands the customer where the
 * payment would have before (GatewayPaymentLandingController).
 *
 * See .ai/rules/payment-gateway-returns.md.
 */
class GatewayReturn
{
    public const string FINISHED_ROUTE = 'gateway_payment.finished';

    public const string OUTCOME_ROUTE = 'gateway_payment.outcome';

    /** How long the signed link to the finished page is valid, in minutes. */
    public const int FINISHED_LINK_MINUTES = 30;

    /**
     * A reference for SSLCommerz's `tran_id` or AamarPay's `tran_id`: 24 random
     * characters (a CSPRNG), 28 with the prefix, under SSLCommerz's 30.
     */
    public static function newReference(): string
    {
        return 'WEB-'.Str::random(24);
    }

    /**
     * Record the payment `$payment` describes (SessionGatewayPayment::pending())
     * as pending under `$reference`, with the converted amount the gateway is
     * charged, its currency and an expiry.
     *
     * @param  array{user_id: int, payment_type: string, combined_order_id: int|null, package_id: int|null, amount: float}  $payment
     */
    public function begin(string $gateway, string $reference, array $payment, float $gatewayAmount, string $gatewayCurrency): GatewayPayment
    {
        return app(GatewayPaymentService::class)->begin(
            $gateway, $reference, $payment['user_id'], $payment['payment_type'], $payment['combined_order_id'], $payment['package_id'],
            $payment['amount'], round($gatewayAmount, 2), strtoupper($gatewayCurrency),
            now()->addMinutes((int) config('store.gateway_return_ttl_minutes', 180)),
        );
    }

    /**
     * The payment a return names, or why it is refused: `unknown` (no row, or a
     * row the storefront did not start, such as the mobile API's), `wrong_gateway`
     * or `expired`. A completed row is returned as it is: the caller sends the
     * browser to its finished page without crediting anything.
     */
    public function find(string $gateway, string $reference): GatewayPayment|string
    {
        if ($reference === '' || strlen($reference) > 191) {
            return 'unknown';
        }

        $rows = GatewayPayment::query()->where('reference', $reference)->get();
        $row = $rows->firstWhere('gateway', $gateway);

        if ($row === null) {
            return $rows->isEmpty() ? 'unknown' : 'wrong_gateway';
        }

        if ($row->completed_at !== null) {
            return $row;
        }

        if ($row->gateway_amount === null || $row->gateway_currency === null || $row->expires_at === null) {
            return 'unknown';
        }

        return $row->hasExpired() ? 'expired' : $row;
    }

    /**
     * Credit `$pending` once, when the gateway's server reported exactly the
     * converted amount recorded at the start, to the cent, in its currency. A
     * mismatch leaves the row pending (the reference is not used up). A gateway
     * that reports no currency at all (`$reportedCurrency` null or blank) is
     * compared in the recorded currency; a reported one must equal it.
     */
    public function complete(GatewayPayment $pending, float $reportedAmount, ?string $reportedCurrency, ?string $details): RedirectResponse
    {
        $reportedCurrency = trim((string) $reportedCurrency);

        if ($reportedCurrency === '') {
            $reportedCurrency = (string) $pending->gateway_currency;
        }

        if (strtoupper($reportedCurrency) !== (string) $pending->gateway_currency
            || ! self::sameCents($reportedAmount, (float) $pending->gateway_amount)) {
            return $this->refuse('amount_mismatch', $pending->gateway, $pending->reference, $pending);
        }

        $payment = new VerifiedPayment($pending->gateway, $pending->reference, (float) $pending->amount, $details);

        if (app(GatewayPaymentService::class)->completePending($payment)) {
            Log::info('Gateway return credited.', ['gateway' => $pending->gateway, 'payment' => $pending->id]);

            return $this->finished($pending);
        }

        // Another return of the same payment got there first: nothing more is credited.
        if ($pending->fresh()?->completed_at !== null) {
            return $this->finished($pending);
        }

        return $this->refuse('sink_refused', $pending->gateway, $pending->reference, $pending);
    }

    /** A 303 to the signed page that shows the payer their completed payment. */
    public function finished(GatewayPayment $payment): RedirectResponse
    {
        return redirect()->to(URL::temporarySignedRoute(self::FINISHED_ROUTE, now()->addMinutes(self::FINISHED_LINK_MINUTES), ['payment' => $payment->id]), 303);
    }

    /** A refused return: logged (without the payload, the reference shortened), and a 303 to "could not be verified". */
    public function refuse(string $reason, string $gateway, string $reference, ?GatewayPayment $payment = null): RedirectResponse
    {
        Log::warning('Gateway return refused.', array_filter([
            'gateway' => $gateway,
            'reason' => $reason,
            'reference' => self::shortReference($reference),
            'payment' => $payment?->id,
            'hint' => $reason === 'unknown' ? 'no pending record; a payment started before this release, or a forged return' : null,
        ]));

        return $this->outcome('unverified');
    }

    /** A 303 to the page that tells the customer the payment failed, was cancelled or could not be verified. */
    public function outcome(string $outcome): RedirectResponse
    {
        return redirect()->route(self::OUTCOME_ROUTE, ['outcome' => $outcome], 303);
    }

    public static function sameCents(float $a, float $b): bool
    {
        return (int) round($a * 100) === (int) round($b * 100);
    }

    /** Enough of a reference to find it in the database, not enough to replay it from a log. */
    public static function shortReference(string $reference): string
    {
        return $reference === '' ? '' : mb_substr($reference, 0, 6).'…';
    }
}
