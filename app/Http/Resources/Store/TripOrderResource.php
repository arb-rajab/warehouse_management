<?php

namespace App\Http\Resources\Store;

use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\StoreUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the trip orders list, with what the Blade list printed: the
 * customer (for a representative's order, the customer it was placed for)
 * and the representative, the shipping address, country and phone from the
 * order's stored address (else the customer's address and phone), the
 * stored grand total through `single_price()`, the delivery date as
 * `YYYY-MM-DD`, and the order's latest checkpoint with its trip and photo
 * upload ids. `editable` is the Blade list's condition for its Edit link.
 *
 * @property-read Order $resource
 *
 * @mixin Order
 */
class TripOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $customer = $this->customer;
        $representative = $this->representative;
        $address = json_decode((string) $this->shipping_address, true);
        $address = is_array($address) && $address !== [] ? $address : null;
        /** @var Checkpoint|null $checkpoint */
        $checkpoint = $this->checkpoints->last();
        $trip = $checkpoint?->trip;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'is_new' => (int) $this->getAttribute('viewed') === 0,
            'details_count' => (int) $this->getAttribute('order_details_count'),
            'otajer_order_id' => $this->getAttribute('otajerOrderID'),
            'order_from' => $this->getAttribute('order_from'),
            'wms_status' => $this->getAttribute('wms_status'),
            'customer' => $customer === null ? null : $this->person($customer),
            'by_rep' => $this->by_rep,
            'representative' => $representative === null ? null : ['id' => $representative->id, 'name' => $representative->name],
            'address' => $address === null ? $customer?->getAttribute('shipping_address') : ($address['address'] ?? null),
            'country' => $address['country'] ?? null,
            'phone' => $address === null ? $customer?->phone : ($address['phone'] ?? null),
            'invoice_number' => $this->invoice_number,
            'customer_notes' => $this->getAttribute('additional_info'),
            'manager_notes' => $this->getAttribute('manager_notes'),
            'amount' => single_price((float) $this->grand_total),
            'delivery_status' => $this->delivery_status,
            'delivery_date' => $this->deliveryDate(),
            'editable' => ! $this->by_rep && $this->payment_status === 'unpaid' && ! $this->getAttribute('commission_calculated') && ! in_array($this->delivery_status, ['delivered', 'cancelled'], true),
            'checkpoint' => $checkpoint === null ? null : [
                'id' => $checkpoint->id,
                'photos' => array_values(array_map('intval', array_filter(explode(',', (string) $checkpoint->photos), fn (string $id) => ctype_digit($id)))),
            ],
            'trip' => $trip === null ? null : [
                'id' => $trip->id,
                'code' => $trip->code,
                'driver' => $trip->driver === null ? null : ['id' => $trip->driver->id, 'name' => $trip->driver->name],
                'truck' => $trip->truck === null ? null : ['id' => $trip->truck->id, 'name' => $trip->truck->name],
            ],
        ];
    }

    /** @return array{id: int, name: string|null, accounting_id: string|null, company_name: string|null} */
    private function person(StoreUser $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'accounting_id' => $user->getAttribute('AccSysID'),
            'company_name' => $user->getAttribute('company_name'),
        ];
    }

    /** The stored delivery date (free text) as `YYYY-MM-DD`, or null when it is not a date. */
    private function deliveryDate(): ?string
    {
        if (blank($this->delivery_date)) {
            return null;
        }

        try {
            return Carbon::parse($this->delivery_date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
