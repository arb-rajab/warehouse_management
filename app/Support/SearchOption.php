<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\State;
use App\Models\StoreUser;
use App\Models\Upload;

/**
 * The one JSON shape every store back office search endpoint returns for
 * `StoreSearchSelect.vue` (.ai/rules/store-search-select.md), so each select
 * is built the same way and an edit form can send the already-picked value in
 * the same shape. A row shows `label`, `description`, `image` (or the `icon`
 * tile, or the label's initials for `user`) and `badge`; `extra` carries data
 * the page needs after a pick, never anything the row displays. A row's
 * content per entity is defined by the factories below and in the rule.
 *
 * @phpstan-type Badge array{text: string, tone: 'neutral'|'success'|'warning'|'danger'|'info'}
 * @phpstan-type Option array{id: int|string, label: string, description: string|null, image: string|null, icon: string|null, badge: Badge|null, extra?: array<string, mixed>}
 */
final class SearchOption
{
    /** A product with fewer units than this in stock is flagged as low. */
    public const int LOW_STOCK = 10;

    /**
     * @param  Badge|null  $badge
     * @param  array<string, mixed>  $extra
     * @return Option
     */
    public static function make(int|string $id, string $label, ?string $description = null, ?string $image = null, ?string $icon = null, ?array $badge = null, array $extra = []): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'description' => $description,
            'image' => $image,
            'icon' => $icon,
            'badge' => $badge,
            ...($extra === [] ? [] : ['extra' => $extra]),
        ];
    }

    /** @return Badge */
    public static function badge(string $text, string $tone = 'neutral'): array
    {
        /** @var Badge */
        return ['text' => $text, 'tone' => $tone];
    }

    /**
     * The secondary line: the non-empty parts joined with a middle dot.
     *
     * @param  array<int, string|int|float|null>  $parts
     */
    public static function describe(array $parts): ?string
    {
        $text = implode(' · ', array_filter(array_map(fn ($part) => $part === null ? '' : trim((string) $part), $parts), fn (string $part) => $part !== ''));

        return $text === '' ? null : $text;
    }

    /**
     * A customer: name, `phone · email`, the account number as the badge.
     * Select `id, name, email, phone, AccSysID, member_serial, rep_discount_percentage`.
     *
     * @return Option
     */
    public static function customer(StoreUser $user): array
    {
        $account = $user->getAttribute('AccSysID');

        return self::make(
            $user->id,
            (string) $user->name,
            self::describe([$user->phone, $user->email]),
            null,
            'user',
            $account === null || $account === '' ? null : self::badge(__('store_admin.search_option_account', ['id' => $account])),
            [
                'account_id' => $account === null ? null : (string) $account,
                'member_serial' => $user->getAttribute('member_serial'),
                'rep_discount_percentage' => (float) $user->getAttribute('rep_discount_percentage'),
            ],
        );
    }

    /**
     * A representative: name, `phone · email`, the discount as the badge.
     * Select the same columns as for {@see self::customer()}.
     *
     * @return Option
     */
    public static function representative(StoreUser $user): array
    {
        $discount = (float) $user->getAttribute('rep_discount_percentage');

        return self::make(
            $user->id,
            (string) $user->name,
            self::describe([$user->phone, $user->email]),
            null,
            'user',
            $discount > 0 ? self::badge(__('store_admin.search_option_discount', ['percent' => self::trimmed($discount)]), 'info') : null,
            ['rep_discount_percentage' => $discount],
        );
    }

    /**
     * A product of the order form: thumbnail, name in the active language,
     * `SKU · #id · unit price`, and the stock as the badge. Select `id, name,
     * ar_name, serial, unit_price, unit_equal, tax, digital, thumbnail_img` and
     * eager load `stocks` (`id, product_id, variant, price, qty`) and
     * `thumbnailUpload` (`id, file_name, external_link`).
     *
     * @return Option
     */
    public static function orderProduct(Product $product): array
    {
        $stocks = $product->stocks->map(fn ($stock): array => ['variant' => (string) $stock->variant, 'price' => (float) $stock->price, 'qty' => (int) $stock->qty])->all();
        $quantity = array_sum(array_column($stocks, 'qty'));
        $digital = (bool) $product->getAttribute('digital');
        $price = (float) $product->getAttribute('unit_price');
        $serial = $product->getAttribute('serial');

        return self::make(
            $product->id,
            app()->getLocale() === 'ar' ? ((string) ($product->ar_name ?: $product->name)) : (string) $product->name,
            self::describe([$serial === null || $serial === '' ? null : __('store_admin.search_option_sku', ['serial' => $serial]), '#'.$product->id, number_format($price, 2, '.', '')]),
            self::imageUrl($product->thumbnailUpload),
            'package',
            $digital ? null : self::stockBadge($quantity),
            [
                'serial' => $serial,
                'mat_id' => (string) $product->id,
                'unit_price' => $price,
                'unit_equal' => (float) $product->getAttribute('unit_equal'),
                'tax' => (float) $product->getAttribute('tax'),
                'digital' => $digital,
                'stocks' => $stocks,
            ],
        );
    }

    /**
     * A product of the campaign and coupon pickers: thumbnail, name in the
     * active language, `SKU · #id · price`, and the product's own discount as
     * the badge. Select `id, name, serial, unit_price, discount, discount_type,
     * thumbnail_img`, with `product_translations` for the language and
     * `thumbnailUpload` (`id, file_name, external_link`) loaded.
     *
     * @return Option
     */
    public static function campaignProduct(Product $product): array
    {
        $price = (float) $product->getAttribute('unit_price');
        $discount = (float) $product->getAttribute('discount');
        $type = $product->getAttribute('discount_type') ?? 'amount';
        $serial = $product->getAttribute('serial');

        return self::make(
            $product->id,
            (string) $product->getTranslation('name'),
            self::describe([$serial === null || $serial === '' ? null : __('store_admin.search_option_sku', ['serial' => $serial]), '#'.$product->id, number_format($price, 2, '.', '')]),
            self::imageUrl($product->thumbnailUpload),
            'package',
            $discount > 0 ? self::badge(__('store_admin.search_option_off', ['value' => $type === 'percent' ? self::trimmed($discount).'%' : number_format($discount, 2, '.', '')]), 'info') : null,
            ['price' => $price, 'discount' => $discount, 'discount_type' => $type],
        );
    }

    /**
     * A brand: its name, in a tag tile. Select `id, name` (the base name, so
     * no translations are loaded per row).
     *
     * @return Option
     */
    public static function brand(Brand $brand): array
    {
        return self::make($brand->id, (string) $brand->name, null, null, 'tag');
    }

    /** 12.50 as "12.5", 10.00 as "10". */
    private static function trimmed(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * A newsletter recipient: the email is the posted value and the label, a
     * user's name is the description. Select `id, email` (and `name` for a user).
     *
     * @return Option
     */
    public static function recipient(string $email, ?string $name = null): array
    {
        return self::make($email, $email, $name === null || trim($name) === '' ? null : trim($name), null, 'mail');
    }

    /**
     * The order configuration's default product: thumbnail, name, `#id`. Select
     * `id, name, thumbnail_img` with `product_translations` and `thumbnailUpload` loaded.
     *
     * @return Option
     */
    public static function configurationProduct(Product $product): array
    {
        return self::make($product->id, (string) $product->getTranslation('name'), '#'.$product->id, self::imageUrl($product->thumbnailUpload), 'package', null, ['mat_id' => (string) $product->id]);
    }

    /**
     * An order a trip's delivery checkpoint can visit: `#code` with the customer
     * and the total. Select `id, code, by_rep, user_id, for_customer, grand_total`
     * and load `user:id,name` and `rep:id,name`.
     *
     * @return Option
     */
    public static function tripOrder(Order $order): array
    {
        return self::make($order->id, '#'.$order->code, self::describe([$order->customer?->name, number_format((float) $order->grand_total, 2, '.', '')]), null, 'truck');
    }

    /**
     * A state: its name, the country as the description. Select `id, name,
     * country_id` and load `country:id,name`.
     *
     * @return Option
     */
    public static function state(State $state): array
    {
        return self::make($state->id, (string) $state->name, $state->country?->name, null, 'map-pin', null, ['country_id' => (int) $state->country_id]);
    }

    /**
     * A city: its name in the active language, the state as the description.
     * Select `id, name, state_id` and load `city_translations:id,city_id,lang,name`
     * and `state:id,name`.
     *
     * @return Option
     */
    public static function city(City $city): array
    {
        $translation = $city->city_translations->firstWhere('lang', app()->getLocale());

        return self::make($city->id, (string) ($translation->name ?? $city->name), $city->state?->name, null, 'map-pin', null, ['state_id' => (int) $city->state_id]);
    }

    /**
     * A seller (or any user a report names): the name, the email as the description.
     * Select `id, name, email`.
     *
     * @return Option
     */
    public static function user(StoreUser $user): array
    {
        return self::make($user->id, (string) $user->name, self::describe([$user->email]), null, 'user');
    }

    /** @return Badge */
    public static function stockBadge(int $quantity): array
    {
        if ($quantity <= 0) {
            return self::badge(__('store_admin.search_option_out_of_stock'), 'danger');
        }

        return $quantity < self::LOW_STOCK
            ? self::badge(__('store_admin.search_option_low_stock', ['qty' => $quantity]), 'warning')
            : self::badge(__('store_admin.search_option_in_stock', ['qty' => $quantity]), 'success');
    }

    /** The upload's list thumbnail, or null without one or when the URL is not http(s). */
    public static function imageUrl(?Upload $upload): ?string
    {
        if ($upload === null) {
            return null;
        }

        $url = thumbnail_asset($upload->id, $upload);

        return is_string($url) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) ? $url : null;
    }
}
