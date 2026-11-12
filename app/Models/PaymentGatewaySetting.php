<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One saved payment gateway field (payment_gateway_settings): written and read
 * only by App\Support\StorePaymentGateways (.ai/rules/admin-env-overrides.md).
 * A secret's `value` is ciphertext; `value` is hidden so a row can never be
 * serialised into a response or a log by accident.
 *
 * @property int $id
 * @property string $gateway
 * @property string $field
 * @property string $value
 * @property bool $is_secret
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Table('payment_gateway_settings')]
#[Fillable(['gateway', 'field', 'value', 'is_secret', 'updated_by'])]
#[Hidden(['value'])]
class PaymentGatewaySetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
            'updated_by' => 'integer',
        ];
    }
}
