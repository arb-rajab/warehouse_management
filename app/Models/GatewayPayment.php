<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A payment a store gateway reported, recorded once under the gateway's own
 * reference (see GatewayPaymentService). A row with no `completed_at` is a
 * mobile-API payment that was started but whose callback has not come back yet.
 *
 * @property int $id
 * @property string $gateway
 * @property string $reference
 * @property int $user_id
 * @property string $payment_type
 * @property int|null $combined_order_id
 * @property int|null $package_id
 * @property string $amount
 * @property Carbon|null $completed_at
 */
#[Fillable(['gateway', 'reference', 'user_id', 'payment_type', 'combined_order_id', 'package_id', 'amount', 'completed_at'])]
class GatewayPayment extends Model
{
    protected $table = 'wms_gateway_payments';

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
            'completed_at' => 'datetime',
        ];
    }
}
