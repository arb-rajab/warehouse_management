<?php

namespace App\Http\Resources\Store;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 *
 * @property-read string $unit_price
 * @property-read string $discount
 * @property-read string $discount_type
 */
class CampaignProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => (string) $this->getTranslation('name'), 'price' => (float) $this->unit_price, 'discount' => (float) $this->discount, 'discount_type' => $this->discount_type ?? 'amount'];
    }
}
