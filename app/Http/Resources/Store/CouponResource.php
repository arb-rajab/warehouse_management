<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $type
 * @property-read string $code
 * @property-read string $details
 * @property-read string $discount
 * @property-read string $discount_type
 * @property-read int $start_date
 * @property-read int $end_date
 */
class CouponResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $details = json_decode($this->details, true) ?? [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'code' => $this->code,
            'discount' => (float) $this->discount,
            'discount_type' => $this->discount_type,
            'start_date' => date('Y-m-d', $this->start_date),
            'end_date' => date('Y-m-d', $this->end_date),
            'product_ids' => $this->type === 'product_base' ? array_map('intval', array_column($details, 'product_id')) : [],
            'min_buy' => $this->type === 'cart_base' ? (float) ($details['min_buy'] ?? 0) : null,
            'max_discount' => $this->type === 'cart_base' ? (float) ($details['max_discount'] ?? 0) : null,
        ];
    }
}
