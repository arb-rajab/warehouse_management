<?php

namespace App\Http\Resources\Store;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Services\StoreAdminOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read Order $resource
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public const array DETAIL_COLUMNS = ['id', 'order_id', 'product_id', 'product_name', 'product_serial', 'product_matId', 'quantity', 'price', 'original_price', 'tax', 'shipping_cost', 'profits', 'variation', 'variation_qty', 'entry_unit', 'item_notes'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'invoice_number' => $this->invoice_number,
            'customer' => $this->whenLoaded('user', fn () => $this->customer?->only(['id', 'name', 'AccSysID'])),
            'representative' => $this->whenLoaded('user', fn () => $this->representative?->only(['id', 'name', 'rep_discount_percentage'])),
            'editable' => $this->payment_status === 'unpaid' && ! $this->getAttribute('commission_calculated') && ! in_array($this->delivery_status, ['delivered', 'cancelled'], true),
            'by_rep' => $this->by_rep, 'delivery_date' => $this->delivery_date,
            'delivery_status' => $this->delivery_status, 'payment_status' => $this->payment_status,
            'payment_type' => $this->payment_type, 'grand_total' => (float) $this->grand_total,
            'profits' => (float) $this->getAttribute('profits'), 'discount_percent' => (float) $this->getAttribute('discount_percent'),
            'indicator' => (float) $this->getAttribute('indicator'), 'manager_notes' => $this->getAttribute('manager_notes'),
            'created_at' => $this->created_at->toIso8601String(),
            'details' => $this->whenLoaded('orderDetails', fn () => $this->orderDetails->map(fn (OrderDetail $line): array => [
                'id' => $line->id, 'product_id' => $line->product_id, 'name' => $line->getAttribute('product_name'),
                'serial' => $line->getAttribute('product_serial'), 'mat_id' => $line->getAttribute('product_matId'),
                'quantity' => (int) $line->quantity, 'price' => (float) $line->price, 'unit_price' => $line->quantity > 0 ? round((float) $line->price / $line->quantity, 2) : 0,
                'original_price' => (float) $line->getAttribute('original_price'), 'tax' => (float) $line->tax,
                'shipping_cost' => (float) $line->shipping_cost, 'profits' => (float) $line->getAttribute('profits'),
                'variation' => app(StoreAdminOrderService::class)->stockVariant($line), 'variation_qty' => $line->getAttribute('variation_qty'),
                'unit' => $line->getAttribute('entry_unit') ?? (in_array($line->variation, ['PerBox', 'PerPiece'], true) ? $line->variation : 'PerBox'),
                'info' => $line->getAttribute('item_notes'),
            ])->all()),
        ];
    }
}
