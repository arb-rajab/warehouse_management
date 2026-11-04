<?php

namespace App\Http\Middleware;

use App\Models\StoreUser;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prices are for verified customers: a guest and a customer whose
 * `admin_verified` is false (the back office's Customers, Verify) get the price
 * fields of a store API answer as null, so no client can show them by accident.
 * Staff, sellers, drivers and representatives are not affected.
 *
 * The route says which answer it is guarding: `hide.prices:catalog` for the
 * product lists, search, detail, variants and wishlist, `hide.prices:cart` for
 * the cart and its summary. The key lists are the money fields those answers
 * carry, wherever they sit in the JSON; a new money field has to be added here.
 * An answer that is an object also gets `price_hidden: true`, and every hidden
 * answer carries the header `X-Prices-Hidden: 1`.
 */
class HidePricesFromUnverified
{
    /** @var array<string, list<string>> */
    public const array KEYS = [
        'catalog' => [
            'stroked_price', 'main_price', 'discounted_price', 'discounted_value', 'base_price',
            'base_discounted_price', 'price_per_piece', 'calculable_price', 'price', 'unit_price',
        ],
        'cart' => [
            'sub_total', 'tax', 'tax_amount', 'shipping_cost', 'discount', 'grand_total', 'grand_total_value',
            'delivery_cost', 'default_delivery_fees', 'price', 'rep_price', 'subtotal',
        ],
    ];

    public function handle(Request $request, Closure $next, string $answer = 'catalog'): Response
    {
        $response = $next($request);

        if (! $response instanceof JsonResponse || ! $response->isSuccessful() || ! self::hidesPricesFrom(auth('store_api')->user())) {
            return $response;
        }

        $data = $response->getData(true);

        if (! is_array($data)) {
            return $response;
        }

        $data = self::blank($data, self::KEYS[$answer]);

        if (! array_is_list($data)) {
            $data['price_hidden'] = true;
        }

        return $response->setData($data)->header('X-Prices-Hidden', '1');
    }

    /** A guest, or a customer the back office has not verified. */
    public static function hidesPricesFrom(?object $user): bool
    {
        if (! $user instanceof StoreUser) {
            return true;
        }

        return $user->user_type === 'customer' && ! $user->is_rep && ! $user->admin_verified;
    }

    /**
     * @param  array<mixed>  $data
     * @param  list<string>  $keys
     * @return array<mixed>
     */
    private static function blank(array $data, array $keys): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, $keys, true)) {
                $data[$key] = null;
            } elseif (is_array($value)) {
                $data[$key] = self::blank($value, $keys);
            }
        }

        return $data;
    }
}
