<?php

namespace App\Services;

use App\Models\Currency;

/**
 * The currency a payment gateway charges in, against the currency the store
 * prices in.
 *
 * The store's amounts (order totals, package prices, wallet top-ups) are in its
 * default currency. A gateway that charges in another currency has to be sent
 * the converted amount, and what it reports back has to be read in that
 * currency. Conversion follows the store's own rule (`convert_price()`):
 * `amount / default rate * gateway rate`, from the `currencies` table.
 *
 * A gateway currency the store has no active rate for returns null: the caller
 * refuses the payment, because charging the unconverted figure charges the
 * wrong sum. A store with no default currency configured prices nothing, so it
 * is treated as already being in the gateway's currency (no conversion).
 */
final class GatewayCurrency
{
    public function __construct(public readonly string $code) {}

    /** SSLCommerz charges in BDT unless `SSLCZ_CURRENCY` says otherwise. */
    public static function sslcommerz(): self
    {
        return new self(strtoupper((string) config('services.sslcommerz.currency', 'BDT')));
    }

    /** Whether the store already prices in this currency, so no conversion applies. */
    public function isStoreCurrency(): bool
    {
        $default = $this->defaultCurrency();

        return $default === null || strtoupper((string) $default->code) === $this->code;
    }

    /** `$amount` in the gateway's currency, or null when the store has no rate for it. */
    public function fromStore(float $amount): ?float
    {
        if ($this->isStoreCurrency()) {
            return round($amount, 2);
        }

        $default = $this->defaultCurrency();
        $gateway = Currency::query()->where('code', $this->code)->where('status', 1)->orderByDesc('id')->first();

        if ($default === null || $gateway === null) {
            return null;
        }

        $defaultRate = (float) $default->exchange_rate;
        $gatewayRate = (float) $gateway->exchange_rate;

        if ($defaultRate <= 0 || $gatewayRate <= 0) {
            return null;
        }

        return round($amount / $defaultRate * $gatewayRate, 2);
    }

    /**
     * The amount to credit, in the store's currency, once the gateway's own
     * validation has confirmed the payment.
     *
     * In the store's own currency the gateway's reported amount is compared as
     * it came. In another currency the validation already matched the gateway's
     * amount to the converted amount due, so the amount due was paid: the
     * reported figure is in the other currency and cannot be compared to it.
     */
    public function paidInStoreCurrency(float $reported, float $due): float
    {
        return $this->isStoreCurrency() ? $reported : $due;
    }

    private function defaultCurrency(): ?Currency
    {
        $id = get_setting('system_default_currency');

        return $id ? Currency::query()->whereKey($id)->first() : null;
    }
}
