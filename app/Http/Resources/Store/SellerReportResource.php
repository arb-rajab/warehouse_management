<?php

namespace App\Http\Resources\Store;

use App\Models\StoreUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string|null $name
 * @property-read StoreUser|null $user
 * @property-read mixed $report_sales
 * @property-read mixed $report_amount
 */
class SellerReportResource extends JsonResource
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
            'seller_name' => $this->user?->name,
            'shop_name' => $this->name,
            'num_of_sale' => (int) $this->report_sales,
            'order_amount' => single_price((float) $this->report_amount),
        ];
    }
}
