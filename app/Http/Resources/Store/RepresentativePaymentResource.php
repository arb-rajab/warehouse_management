<?php

namespace App\Http\Resources\Store;

use App\Models\StoreUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int|string $id
 * @property-read string $amount
 * @property-read string $receipt_code
 * @property-read int|null $invoice_code
 * @property-read int|null $otajer_id
 * @property-read string|null $customer_name
 * @property-read string|null $notes
 * @property-read string $status
 * @property-read string $date
 * @property-read StoreUser|null $rep
 * @property-read StoreUser|null $customer
 * @property-read StoreUser|null $admin
 */
class RepresentativePaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'amount' => (string) $this->amount,
            'receipt_code' => $this->receipt_code,
            'invoice_code' => $this->invoice_code,
            'notes' => $this->notes,
            'status' => $this->status,
            'date' => $this->date,
            'customer_name' => $this->customer === null ? $this->customer_name : $this->customer->name,
            'account_id' => $this->otajer_id ?? ($this->customer === null ? null : $this->customer->AccSysID),
            'representative' => $this->whenLoaded('rep', fn () => $this->rep === null ? null : ['id' => $this->rep->id, 'name' => $this->rep->name]),
            'accountant' => $this->whenLoaded('admin', fn () => $this->admin === null ? null : ['id' => $this->admin->id, 'name' => $this->admin->name]),
        ];
    }
}
