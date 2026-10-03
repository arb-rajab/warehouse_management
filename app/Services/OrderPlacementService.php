<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Product;
use App\Models\ProductStock;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * The two steps of placing an order that must not be raced: taking stock and
 * using a single-use coupon. Both checkouts (the storefront's and the app's)
 * call this inside the transaction that creates the order, so a refusal rolls
 * the whole order back.
 *
 * Both read the row under a lock and write in the same transaction: stock was
 * read, compared and written back as a plain `qty -= n`, so two checkouts of
 * the last unit both succeeded; a coupon was checked when it was applied and
 * only recorded when the order was saved, so two checkouts both used it.
 */
class OrderPlacementService
{
    /**
     * Take `$quantity` of `$product`'s `$variation` out of stock. A digital
     * product has none to take.
     *
     * @throws DomainException with the message to show the customer when the stock row is
     *                         missing or holds less than `$quantity`
     */
    public function reserveStock(Product $product, ?string $variation, int|float $quantity): void
    {
        if ($product->digital == 1) {
            return;
        }

        $stock = ProductStock::query()
            ->where('product_id', $product->id)
            ->where('variant', 'like', '%'.$variation.'%')
            ->lockForUpdate()
            ->first();

        if ($stock === null || $quantity > $stock->qty) {
            throw new DomainException(translate('The requested quantity is not available for ').$product->name);
        }

        $stock->decrement('qty', $quantity);
    }

    /**
     * Use coupon `$code` for `$userId` on `$lines` and return the discount the
     * order takes off. The coupon must exist, be in date, apply to these lines
     * and not have been used by this customer before.
     *
     * @param  Collection<int, Cart>  $lines  the order's cart lines
     *
     * @throws DomainException with the message to show the customer when the coupon is refused
     */
    public function useCoupon(int $userId, string $code, Collection $lines): float
    {
        $coupon = Coupon::query()->where('code', $code)->lockForUpdate()->first();

        if ($coupon === null) {
            throw new DomainException(translate('Invalid coupon!'));
        }

        $today = strtotime(date('d-m-Y'));

        if ($today < $coupon->start_date || $today > $coupon->end_date) {
            throw new DomainException(translate('Coupon expired!'));
        }

        $alreadyUsed = CouponUsage::query()->where('user_id', $userId)->where('coupon_id', $coupon->id)->exists();

        if ($alreadyUsed) {
            throw new DomainException(translate('You already used this coupon!'));
        }

        $discount = app(CouponService::class)->discountFor($coupon, $lines->where('owner_id', $coupon->user_id));

        if ($discount <= 0) {
            throw new DomainException(translate('This coupon is not applicable to your cart products!'));
        }

        $usage = new CouponUsage;
        $usage->user_id = $userId;
        $usage->coupon_id = $coupon->id;

        try {
            $usage->save();
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(translate('You already used this coupon!'));
        }

        return $discount;
    }
}
