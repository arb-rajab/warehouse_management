<?php

namespace App\Http\Resources\Store;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read Order|null $order
 * @property-read string $admin_commission
 * @property-read string $seller_earning
 * @property-read CarbonInterface $created_at
 */
class CommissionReportResource extends JsonResource
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
            'order_code' => $this->order?->code,
            'admin_commission' => (string) $this->admin_commission,
            'seller_earning' => (string) $this->seller_earning,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
