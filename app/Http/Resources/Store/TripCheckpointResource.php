<?php

namespace App\Http\Resources\Store;

use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\StoreUser;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the trip detail, with what the Blade checkpoints page printed: the
 * customer (for an order, the customer it was placed for) with their phone,
 * company and stored shipping address, the representative who placed the
 * order, the order's code, invoice number, product count and stored grand
 * total through `single_price()`, the status and arrival, the estimated time
 * in seconds as stored, the notes, and the photo upload ids. The CMR file is
 * only flagged: the download resolves it (`checkpoints.print-cmr`).
 *
 * @property-read int $id
 * @property-read int|null $sort_order_in_trip
 * @property-read string $type
 * @property-read string $status
 * @property-read CarbonInterface|null $arrived_at
 * @property-read float|null $eta
 * @property-read string|null $notes
 * @property-read string|null $cmr_file
 * @property-read string|null $photos
 */
class TripCheckpointResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Checkpoint $checkpoint */
        $checkpoint = $this->resource;
        $related = $checkpoint->getRelationValue('relationable');
        $order = $related instanceof Order ? $related : null;
        $customer = $checkpoint->getCustomerForCheckpoint();
        $representative = $order?->representative;

        return [
            'id' => $this->id,
            'position' => $this->sort_order_in_trip,
            'type' => $this->type,
            'status' => $this->status,
            'arrived_at' => $this->arrived_at?->toIso8601String(),
            'eta' => $this->eta === null ? null : (int) round($this->eta),
            'notes' => $this->notes,
            'order' => $order === null ? null : [
                'id' => $order->id,
                'code' => (string) $order->code,
                'invoice_number' => $order->invoice_number,
                'products' => (int) $order->getAttribute('order_details_count'),
                'amount' => single_price((float) $order->grand_total),
            ],
            'customer' => $customer instanceof StoreUser ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'company_name' => $customer->getAttribute('company_name'),
                'address' => $customer->getAttribute('shipping_address'),
            ] : null,
            'representative' => $representative === null ? null : ['id' => $representative->id, 'name' => $representative->name],
            'has_cmr' => self::uploadIds($this->cmr_file) !== [],
            'photos' => self::uploadIds($this->photos),
        ];
    }

    /**
     * The upload ids in a stored comma-separated list, skipping anything that
     * is not an id.
     *
     * @return list<int>
     */
    public static function uploadIds(?string $ids): array
    {
        return array_values(array_map('intval', array_filter(explode(',', (string) $ids), fn (string $id) => ctype_digit($id))));
    }
}
