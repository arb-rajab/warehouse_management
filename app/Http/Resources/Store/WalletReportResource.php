<?php

namespace App\Http\Resources\Store;

use App\Models\StoreUser;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read StoreUser|null $user
 * @property-read string $amount
 * @property-read string|null $payment_method
 * @property-read CarbonInterface $created_at
 */
class WalletReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_name' => $this->user?->name,
            'amount' => single_price((float) $this->amount),
            'payment_method' => $this->payment_method === null ? null : ucfirst(str_replace('_', ' ', $this->payment_method)),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
