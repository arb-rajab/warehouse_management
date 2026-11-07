<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use DomainException;
use Illuminate\Support\Collection;

/**
 * The store's minimum order (back office, Order configuration). With
 * `minimum_order_amount_check` on, a cart whose lines come to less than
 * `minimum_order_amount` before VAT is ordered with one extra line of the fee
 * product (`default_specific_product`); with no fee product to add, it is
 * refused. Lines of the fee product itself do not count toward the minimum.
 *
 * The app's order/store, the web checkout and GET /cart-summary all ask here,
 * so the fee the summary shows is the fee the order charges.
 */
final class MinimumOrderFee
{
    /**
     * The fee product these cart lines need, or null when they need none.
     *
     * @param  Collection<int, Cart>  $lines
     *
     * @throws DomainException when the lines are below the minimum and no fee product exists
     */
    public function productFor(Collection $lines): ?Product
    {
        if ((int) get_setting('minimum_order_amount_check') !== 1 || $lines->isEmpty()) {
            return null;
        }

        $minimum = (float) get_setting('minimum_order_amount');
        $feeId = $this->feeProductId();

        if ($this->subtotal($lines, $feeId) >= $minimum) {
            return null;
        }

        $product = $feeId === null ? null : Product::query()->find($feeId);

        if ($product === null) {
            throw new DomainException(__('api.order.below_minimum', ['amount' => format_price($minimum)]));
        }

        return $product;
    }

    /**
     * Add the fee line to the cart when it needs one, and return the lines with
     * it. Call it inside the order's transaction: a refused order then takes
     * the fee line back with it instead of leaving it in the cart.
     *
     * @param  Collection<int, Cart>  $lines
     * @return Collection<int, Cart>
     *
     * @throws DomainException when the lines are below the minimum and no fee product exists
     */
    public function addTo(Collection $lines): Collection
    {
        $product = $this->productFor($lines);

        if ($product === null || $lines->contains(fn (Cart $line): bool => $this->isFeeLine($line))) {
            return $lines;
        }

        $first = $lines->first();
        $fee = new Cart;
        // Who, where and how it ships come from the cart's first line, so the
        // fee lands in the same order; carrier_id and pickup_point are not fillable.
        $fee->forceFill([
            'user_id' => $first->user_id,
            'temp_user_id' => $first->temp_user_id,
            'owner_id' => $product->user_id,
            'address_id' => $first->address_id,
            'shipping_type' => $first->shipping_type,
            'carrier_id' => $first->carrier_id,
            'pickup_point' => $first->pickup_point,
            'by_rep' => $first->getAttribute('by_rep'),
            'for_customer' => $first->getAttribute('for_customer'),
            'product_id' => $product->id,
            'variation' => '',
            'quantity' => 1,
            'price' => $product->getAttribute('unit_price'),
        ])->save();

        $fee->refresh();

        if ($first->relationLoaded('rep_customer')) {
            $fee->setRelation('rep_customer', $first->getRelation('rep_customer'));
        }

        return $lines->concat([$fee])->values();
    }

    /**
     * Whether this cart line is the fee: it is not dropped when the fee product
     * is unpublished (a fee is usually hidden from the catalogue) and takes no stock.
     */
    public function isFeeLine(Cart $line): bool
    {
        return (int) get_setting('minimum_order_amount_check') === 1
            && $this->feeProductId() !== null
            && (int) $line->product_id === $this->feeProductId();
    }

    private function feeProductId(): ?int
    {
        $id = get_setting('default_specific_product');

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * The lines' price before VAT, a representative's own price where the line
     * has one, as order/store charges it.
     *
     * @param  Collection<int, Cart>  $lines
     */
    private function subtotal(Collection $lines, ?int $feeId): float
    {
        return (float) $lines
            ->reject(fn (Cart $line): bool => $feeId !== null && (int) $line->product_id === $feeId)
            ->sum(function (Cart $line): float {
                if ($line->getAttribute('is_price_changed')) {
                    return (float) $line->getAttribute('rep_price') * $line->quantity;
                }

                $product = Product::query()->find($line->product_id);

                return ($product ? (float) cart_product_price($line, $product, false, false) : (float) $line->price) * $line->quantity;
            });
    }
}
