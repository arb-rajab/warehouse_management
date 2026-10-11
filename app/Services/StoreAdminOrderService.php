<?php

namespace App\Services;

use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StoreUser;
use App\Utility\NotificationUtility;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Atomic administrative order entry, separate from the public cart checkout. */
class StoreAdminOrderService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, int $actorId, bool $representativeOrder): Order
    {
        try {
            return DB::transaction(function () use ($data, $actorId, $representativeOrder): Order {
                StoreUser::query()->whereKey($actorId)->lockForUpdate()->firstOrFail();
                $hash = hash('sha256', json_encode([$representativeOrder, $data], JSON_THROW_ON_ERROR));
                $previous = DB::table('wms_store_order_submissions')->where('submission_key', $data['submission_key'])->first();
                if ($previous !== null) {
                    if ($previous->actor_id != $actorId || $previous->payload_hash !== $hash) {
                        throw ValidationException::withMessages(['submission_key' => __('messages.order_submission_conflict')]);
                    }

                    return Order::query()->whereKey($previous->order_id)->firstOrFail();
                }
                $customer = StoreUser::query()->lockForUpdate()->whereKey($data['customer_id'])->firstOrFail();
                $buyer = $representativeOrder ? StoreUser::query()->lockForUpdate()->whereKey($data['rep_id'])->firstOrFail() : $customer;
                $address = $this->address($customer);
                $combined = new CombinedOrder;
                $combined->user_id = $buyer->id;
                $combined->shipping_address = json_encode($address, JSON_THROW_ON_ERROR);
                $combined->grand_total = 0;
                $combined->save();
                $products = Product::query()->whereIn('id', array_column($data['products'], 'product_id'))
                    ->with('collection:id,amount')->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                $groups = [];
                foreach ($data['products'] as $line) {
                    $product = $products->get($line['product_id']);
                    if ($product === null) {
                        throw ValidationException::withMessages(['products' => __('messages.order_product_unavailable')]);
                    }
                    $groups[$product->user_id][] = $line;
                }
                $first = null;
                foreach ($groups as $sellerId => $lines) {
                    if (! $sellerId) {
                        throw ValidationException::withMessages(['products' => __('messages.order_product_unavailable')]);
                    }
                    $order = new Order;
                    $order->forceFill([
                        'combined_order_id' => $combined->id, 'user_id' => $buyer->id,
                        'seller_id' => $sellerId, 'by_rep' => $representativeOrder,
                        'for_customer' => $representativeOrder ? $customer->id : null,
                        'shipping_address' => $combined->shipping_address, 'shipping_type' => 'home_delivery',
                        'order_from' => 'web', 'payment_type' => 'cash_on_delivery', 'payment_status' => 'unpaid',
                        'delivery_status' => $representativeOrder ? 'pending' : 'ready_for_delivery',
                        'delivery_viewed' => 0, 'payment_status_viewed' => 0,
                        'company_address' => $buyer->getAttribute('company_address'),
                        'company_shipping_address' => $buyer->getAttribute('shipping_address'),
                        'company_tax_number' => $buyer->getAttribute('tax_number'),
                        'member_serial' => $customer->getAttribute('member_serial'),
                        'additional_info' => $data['additional_info'] ?? null, 'delivery_date' => $data['delivery_date'] ?? null,
                        'code' => now()->format('Ymd-His').'-'.Str::random(8), 'date' => now()->timestamp, 'grand_total' => 0,
                    ])->save();
                    foreach ($lines as $line) {
                        $this->writeLine($order, $products->get($line['product_id']), $line);
                    }
                    $this->recalculate($order);
                    $first ??= $order;
                }
                if ($first === null) {
                    throw ValidationException::withMessages(['products' => __('messages.order_product_unavailable')]);
                }
                $profit = (float) $combined->orders()->sum('profits');
                foreach (array_unique([$buyer->id, $customer->id]) as $userId) {
                    StoreUser::query()->whereKey($userId)->update(['last_order_at' => now()->toDateString()]);
                    StoreUser::query()->whereKey($userId)->whereNull('profits')->update(['profits' => 0]);
                    StoreUser::query()->whereKey($userId)->increment('profits', $profit);
                }
                DB::table('wms_store_order_submissions')->insert(['submission_key' => $data['submission_key'], 'actor_id' => $actorId, 'order_id' => $first->id, 'payload_hash' => $hash, 'created_at' => now()]);

                return $first;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (DB::table('wms_store_order_submissions')->where('submission_key', $data['submission_key'])->exists()) {
                throw ValidationException::withMessages(['submission_key' => __('messages.order_submission_conflict')]);
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(Order $found, array $data): void
    {
        DB::transaction(function () use ($found, $data): void {
            $order = $this->lock($found);
            $this->assertEditable($order);
            abort_if($order->by_rep, 403);
            $oldProfit = (float) $order->getAttribute('profits');
            $oldUser = $order->user_id;
            if ($data['customer_id'] != $oldUser && $order->combined_order_id !== null && Order::query()->where('combined_order_id', $order->combined_order_id)->whereKeyNot($order->id)->exists()) {
                throw ValidationException::withMessages(['customer_id' => __('messages.order_combined_customer')]);
            }
            $existing = $order->orderDetails()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($existing as $line) {
                $this->restock($line);
                Product::query()->whereKey($line->product_id)->decrement('num_of_sale', $line->quantity);
            }
            $kept = [];
            foreach ($data['products'] as $dataLine) {
                $product = Product::query()->with('collection:id,amount')->lockForUpdate()->whereKey($dataLine['product_id'])->firstOrFail();
                if ($product->user_id != $order->seller_id) {
                    throw ValidationException::withMessages(['products' => __('messages.order_seller_mismatch')]);
                }
                $line = isset($dataLine['id']) ? $existing->get($dataLine['id']) : null;
                if (isset($dataLine['id']) && $line === null) {
                    throw ValidationException::withMessages(['products' => __('messages.order_line_invalid')]);
                }
                $kept[] = $this->writeLine($order, $product, $dataLine, $line)->id;
            }
            $order->orderDetails()->whereNotIn('id', $kept)->delete();
            $customer = StoreUser::query()->lockForUpdate()->whereKey($data['customer_id'])->firstOrFail();
            $order->forceFill(['user_id' => $customer->id, 'member_serial' => $customer->getAttribute('member_serial'),
                'shipping_address' => json_encode($this->address($customer), JSON_THROW_ON_ERROR),
                'company_address' => $customer->getAttribute('company_address'), 'company_shipping_address' => $customer->getAttribute('shipping_address'), 'company_tax_number' => $customer->getAttribute('tax_number'),
                'delivery_date' => $data['delivery_date'] ?? null, 'additional_info' => $data['additional_info'] ?? null])->save();
            CombinedOrder::query()->whereKey($order->combined_order_id)->update(['user_id' => $customer->id, 'shipping_address' => $order->shipping_address]);
            $this->recalculate($order);
            StoreUser::query()->whereKey($oldUser)->decrement('profits', $oldProfit);
            StoreUser::query()->whereKey($customer->id)->whereNull('profits')->update(['profits' => 0]);
            StoreUser::query()->whereKey($customer->id)->increment('profits', (float) $order->getAttribute('profits'));
            $order->load('checkpoints:id,relationable_id,relationable_type,trip_id', 'checkpoints.trip:id,driver_id', 'checkpoints.trip.driver:id,device_token');
            $drivers = $order->checkpoints->map(fn ($checkpoint) => $checkpoint->trip?->driver)->filter()->unique('id');
            DB::afterCommit(function () use ($drivers, $order): void {
                foreach ($drivers as $driver) {
                    if ($driver->getAttribute('device_token')) {
                        NotificationUtility::sendPushNotificationToUser($driver, __('messages.order_updated_title'), __('messages.order_updated_body', ['code' => $order->code]));
                    }
                }
            });
        });
    }

    public function assertEditable(Order $order): void
    {
        if ($order->payment_status !== 'unpaid' || $order->getAttribute('commission_calculated') || in_array($order->delivery_status, ['delivered', 'cancelled'], true)) {
            throw ValidationException::withMessages(['action' => __('messages.order_not_editable')]);
        }
    }

    /** @param array<string, mixed> $data */
    private function writeLine(Order $order, Product $product, array $data, ?OrderDetail $existing = null): OrderDetail
    {
        if (! $product->published || ! $product->getAttribute('approved')) {
            throw ValidationException::withMessages(['products' => __('messages.order_product_unavailable')]);
        }
        $variant = $data['stock_variant'] ?? '';
        $this->reserve($product, $variant, (int) $data['quantity']);
        $quantity = (int) $data['quantity'];
        $price = round((float) $data['price'] * $quantity, 2);
        $sameProduct = $existing !== null && $existing->product_id == $product->id;
        $original = round((float) $product->getAttribute('unit_price') / ($data['unit'] === 'PerPiece' ? max(1, (float) $product->getAttribute('unit_equal')) : 1) * $quantity, 2);
        $line = $existing ?? new OrderDetail;
        $line->forceFill([
            'order_id' => $order->id, 'seller_id' => $product->user_id, 'product_id' => $product->id,
            'product_serial' => $product->getAttribute('serial'), 'product_matId' => $product->getAttribute('mat_id'),
            'product_name' => $product->ar_name ?: $product->name,
            'variation' => $variant, 'entry_unit' => $data['unit'],
            'variation_qty' => $sameProduct ? $existing->getAttribute('variation_qty') : '[]',
            'price' => $price, 'original_price' => $original, 'is_price_changed' => $price != $original,
            'quantity' => $quantity, 'tax' => $sameProduct ? $existing->tax : round($price * (float) $product->getAttribute('tax') / 100, 2),
            'shipping_cost' => $sameProduct ? $existing->shipping_cost : 0, 'shipping_type' => 'home_delivery',
            'item_notes' => $data['info'] ?? null,
            'profits' => round($price * (float) $product->collection?->getAttribute('amount') / 100, 2),
            'payment_status' => $order->payment_status, 'delivery_status' => $order->delivery_status,
        ])->save();
        $product->increment('num_of_sale', $quantity);

        return $line;
    }

    private function reserve(Product $product, string $variant, int $quantity): void
    {
        try {
            app(OrderPlacementService::class)->reserveStock($product, $variant, $quantity, exactVariant: true);
        } catch (DomainException) {
            throw ValidationException::withMessages(['products' => __('messages.order_stock_unavailable')]);
        }
    }

    /** Legacy feature orders reserved the first stock row while recording a unit label. */
    public function stockVariant(OrderDetail $line): string
    {
        if ($line->getAttribute('entry_unit') === null && in_array($line->variation, ['PerBox', 'PerPiece'], true)) {
            return ProductStock::query()->where('product_id', $line->product_id)->orderBy('id')->value('variant') ?? '';
        }

        return $line->variation ?? '';
    }

    public function restock(OrderDetail $line): void
    {
        if (Product::query()->whereKey($line->product_id)->value('digital')) {
            return;
        }
        $stock = ProductStock::query()->where('product_id', $line->product_id)->where('variant', $this->stockVariant($line))->orderBy('id')->lockForUpdate()->first();
        if ($stock === null) {
            throw ValidationException::withMessages(['products' => __('messages.order_stock_unavailable')]);
        }
        $stock->increment('qty', $line->quantity);
    }

    public function recalculate(Order $order): void
    {
        $lines = $order->orderDetails()->get(['id', 'order_id', 'price', 'original_price', 'tax', 'shipping_cost', 'profits']);
        $price = (float) $lines->sum('price');
        $original = (float) $lines->sum('original_price');
        $profits = (float) $lines->sum('profits');
        $order->forceFill(['grand_total' => max(0, round($price + $lines->sum('tax') + $lines->sum('shipping_cost') - (float) $order->getAttribute('coupon_discount'), 2)),
            'profits' => $profits, 'discount_percent' => $original > 0 ? round(100 - $price / $original * 100, 2) : 0,
            'indicator' => $price > 0 ? round($profits / $price * 100, 2) : 0])->save();
        $combined = CombinedOrder::query()->lockForUpdate()->find($order->combined_order_id);
        if ($combined !== null) {
            $combined->grand_total = $combined->orders()->sum('grand_total');
            $combined->save();
        }
    }

    public function lock(Order $order): Order
    {
        if ($order->combined_order_id !== null) {
            CombinedOrder::query()->whereKey($order->combined_order_id)->lockForUpdate()->first();
        }

        return Order::query()->lockForUpdate()->findOrFail($order->id);
    }

    /** @return array<string, mixed> */
    private function address(StoreUser $customer): array
    {
        $address = $customer->addresses()->with('country:id,name', 'state:id,name', 'city:id,name')->orderByDesc('set_default')->orderBy('id')->first();

        return ['name' => $customer->name, 'email' => $customer->email,
            'address' => $address?->address, 'country' => $address?->country?->name,
            'state' => $address?->state?->name, 'city' => $address?->city?->name,
            'postal_code' => $address?->postal_code, 'phone' => $address !== null ? $address->phone : $customer->phone,
            'lat_lang' => $address !== null && $address->latitude && $address->longitude ? $address->latitude.','.$address->longitude : null];
    }
}
