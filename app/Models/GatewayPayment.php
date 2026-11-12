<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * A payment a store gateway reported, recorded once under the gateway's own
 * reference (see GatewayPaymentService). A row with no `completed_at` is a
 * payment that was started but whose callback has not come back yet: the
 * mobile API's SSLCommerz and Nagad, and the storefront's SSLCommerz, AamarPay
 * and iyzico (App\Services\GatewayReturn, which also sets `gateway_amount`,
 * `gateway_currency` and `expires_at`).
 *
 * @property int $id
 * @property string $gateway
 * @property string $reference
 * @property int $user_id
 * @property string $payment_type
 * @property int|null $combined_order_id
 * @property int|null $package_id
 * @property string $amount
 * @property string|null $gateway_amount
 * @property string|null $gateway_currency
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $expires_at
 */
#[Fillable(['gateway', 'reference', 'user_id', 'payment_type', 'combined_order_id', 'package_id', 'amount', 'gateway_amount', 'gateway_currency', 'completed_at', 'expires_at'])]
class GatewayPayment extends Model
{
    use MassPrunable;

    /** How long an expired pending row is kept for reconciling by hand. */
    public const int PRUNE_AFTER_DAYS = 90;

    protected $table = 'wms_gateway_payments';

    /**
     * Pending rows that expired more than 90 days ago. A completed row is never
     * pruned: it is what stops a reference being credited twice.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNull('completed_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->subDays(self::PRUNE_AFTER_DAYS));
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'combined_order_id' => 'integer',
            'package_id' => 'integer',
            'amount' => 'decimal:2',
            'gateway_amount' => 'decimal:2',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
