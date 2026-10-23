<?php

namespace App\Http\Resources\Store;

use App\Models\StoreUser;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read StoreUser $user
 * @property-read float $amount
 * @property-read string|null $message
 * @property-read int|null $status
 * @property-read CarbonInterface|null $created_at
 */
class SellerWithdrawalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name, 'shop_name' => $this->user->shop?->name]),
            'date' => $this->created_at?->toDateTimeString(),
            'due' => single_price($this->user->shop->admin_to_pay ?? 0),
            'amount' => single_price($this->amount),
            'message' => $this->message,
            'paid' => $this->status == 1,
        ];
    }
}
