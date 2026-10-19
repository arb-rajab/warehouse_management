<?php

namespace App\Http\Resources\Store;

use App\Models\StoreUser;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read StoreUser $seller
 * @property-read float $amount
 * @property-read string|null $payment_method
 * @property-read string|null $txn_code
 * @property-read CarbonInterface|null $created_at
 */
class SellerPaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller' => $this->whenLoaded('seller', fn () => ['id' => $this->seller->id, 'name' => $this->seller->name, 'shop_name' => $this->seller->shop?->name]),
            'date' => $this->created_at?->toDateTimeString(),
            'amount' => single_price($this->amount),
            'payment_method' => ucfirst(str_replace('_', ' ', $this->payment_method ?? '')),
            'txn_code' => $this->txn_code,
        ];
    }
}
