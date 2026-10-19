<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read float $admin_to_pay
 * @property-read int $cash_on_delivery_status
 * @property-read int $bank_payment_status
 * @property-read string|null $bank_name
 * @property-read string|null $bank_acc_name
 * @property-read string|null $bank_acc_no
 * @property-read string|null $bank_routing_no
 */
class SellerPayoutResource extends JsonResource
{
    public const array COLUMNS = ['id', 'user_id', 'admin_to_pay', 'cash_on_delivery_status', 'bank_payment_status', 'bank_name', 'bank_acc_name', 'bank_acc_no', 'bank_routing_no'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'due' => single_price(abs($this->admin_to_pay)),
            'admin_to_pay' => (float) $this->admin_to_pay,
            'cash_enabled' => $this->cash_on_delivery_status == 1,
            'bank_enabled' => $this->bank_payment_status == 1,
            'bank_name' => $this->bank_payment_status == 1 ? $this->bank_name : null,
            'bank_acc_name' => $this->bank_payment_status == 1 ? $this->bank_acc_name : null,
            'bank_acc_no' => $this->bank_payment_status == 1 ? $this->bank_acc_no : null,
            'bank_routing_no' => $this->bank_payment_status == 1 ? (string) $this->bank_routing_no : null,
        ];
    }
}
