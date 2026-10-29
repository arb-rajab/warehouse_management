<?php

namespace App\Http\Resources\Store;

use App\Models\Visit;
use App\Support\StoreLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The fields printed by the former visit detail, plus the note and its owner.
 *
 * @property-read Visit $resource
 *
 * @mixin Visit
 */
class VisitDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'visit_date' => $this->visit_date,
            'representative' => $this->rep === null ? null : ['id' => $this->rep->id, 'name' => $this->rep->name],
            'customer' => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'company_name' => $this->customer->company_name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
                'member_serial' => $this->customer->member_serial,
                'accounting_id' => $this->customer->AccSysID,
            ],
            'order' => $this->order === null ? null : [
                'id' => $this->order->id,
                'code' => $this->order->code,
                'delivery_status' => $this->order->delivery_status,
                'date' => (int) $this->order->date,
                'amount' => single_price((float) $this->order->grand_total),
                'payment_type' => StoreLocale::paymentMethod($this->order->payment_type),
                'customer_notes' => $this->order->additional_info,
                'customer_otajer_id' => $this->order->by_rep ? $this->order->rep?->AccSysID : null,
                'delivery_date' => $this->order->delivery_date,
            ],
        ];
    }
}
