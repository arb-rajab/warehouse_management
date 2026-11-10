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
    /** `$code` is empty when the gateway charges in whatever currency the store prices in. */
    public function __construct(public readonly string $code) {}

    /**
     * The currency `$gateway` is configured to charge in (`config('services.<gateway>.currency')`).
     * Empty means the store's own currency, so no conversion applies.
     */
    public static function forGateway(string $gateway): self
    {
        return new self(strtoupper(trim((string) config("services.$gateway.currency"))));
    }

    /** SSLCommerz charges in BDT unless `SSLCZ_CURRENCY` says otherwise. */
    public static function sslcommerz(): self
    {
        return self::forGateway('sslcommerz');
    }

    /** The currency code to send the gateway: its own, else the store's. Empty only when neither is configured. */
    public function chargeCode(): string
    {
        return $this->code !== '' ? $this->code : strtoupper((string) $this->defaultCurrency()?->code);
    }

    /** An amount as gateways expect it: two decimals, a point, no thousands separator. */
    public static function format(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /** Whether the store already prices in this currency, so no conversion applies. */
    public function isStoreCurrency(): bool
    {
        $default = $this->defaultCurrency();

        return $default === null || $this->code === '' || strtoupper((string) $default->code) === $this->code;
    }

    /** `$amount` in the gateway's currency, or null when the store has no rate for it. */
    public function fromStore(float $amount): ?float
    {
        if ($this->isStoreCurrency()) {
            return round($amount, 2);
        }

        $default = $this->defaultCurrency();
        $gateway = $this->gatewayRow();

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
     * `$amount`, reported by the gateway in its currency, in the store's currency; null when the
     * store has no rate for it. For a payment with no stored amount due (a wallet top-up).
     */
    public function toStore(float $amount): ?float
    {
        if ($this->isStoreCurrency()) {
            return round($amount, 2);
        }

        $default = $this->defaultCurrency();
        $gateway = $this->gatewayRow();

        if ($default === null || $gateway === null || (float) $default->exchange_rate <= 0 || (float) $gateway->exchange_rate <= 0) {
            return null;
        }

        return round($amount / (float) $gateway->exchange_rate * (float) $default->exchange_rate, 2);
    }

    /**
     * Whether the gateway's `$reported` amount is the converted amount due, to the cent: for a
     * payment whose amount due is stored in the store's currency (an order, a package).
     */
    public function matchesDue(float $reported, float $dueInStoreCurrency, float $tolerance = 0.01): bool
    {
        $due = $this->fromStore($dueInStoreCurrency);

        return $due !== null && abs($reported - $due) <= $tolerance + 1e-9;
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

    private function gatewayRow(): ?Currency
    {
        return Currency::query()->where('code', $this->code)->where('status', 1)->orderByDesc('id')->first();
    }

    private function defaultCurrency(): ?Currency
    {
        $id = get_setting('system_default_currency');

        return $id ? Currency::query()->whereKey($id)->first() : null;
    }
}
