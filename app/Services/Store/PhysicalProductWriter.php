<?php

namespace App\Services\Store;

use App\Models\Product;
use App\Support\ProductSlug;

/** Shared column computations and related-row writes for both physical catalogue writers. */
abstract class PhysicalProductWriter extends ProductWriter
{
    /**
     * The fields the form posts straight into `products` columns. A field the
     * form did not show (shipping, flash deal) is not among them, so on
     * create it keeps the column's default and on update its stored value.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function postedColumns(array $data): array
    {
        $columns = [
            'name', 'category_id', 'brand_id', 'unit', 'weight', 'max_qty', 'min_qty', 'photos', 'thumbnail_img',
            'video_provider', 'video_link', 'pdf', 'unit_price', 'discount', 'discount_type', 'current_stock',
            'external_link', 'external_link_btn', 'description', 'low_stock_quantity', 'stock_visibility_state',
            'shipping_type', 'est_shipping_days',
        ];
        $posted = array_intersect_key($data, array_flip($columns));
        if (array_key_exists('weight', $posted)) {
            $posted['weight'] ??= 0;
        }
        foreach (['featured', 'todays_deal', 'is_quantity_multiplied'] as $switch) {
            if (array_key_exists($switch, $data)) {
                $posted[$switch] = (int) (bool) $data[$switch];
            }
        }

        return $posted;
    }

    /**
     * What ProductService computes from the form on create and update alike:
     * the comma-joined tags, the discount period and the meta defaults (the
     * posted name and description, and the thumbnail).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function derivedColumns(array $data): array
    {
        $tags = array_values(array_unique(array_filter(array_map(trim(...), explode(',', (string) ($data['tags'] ?? ''))), fn (string $tag): bool => $tag !== '')));
        $dates = isset($data['date_range']) ? explode(' to ', $data['date_range']) : null;

        return [
            'tags' => implode(',', $tags),
            'discount_start_date' => $dates ? strtotime($dates[0]) : null,
            'discount_end_date' => $dates ? strtotime($dates[1]) : null,
            'meta_title' => $data['meta_title'] ?? $data['name'],
            'meta_description' => $data['meta_description'] ?? strip_tags((string) ($data['description'] ?? '')),
            'meta_img' => $data['meta_img'] ?? $data['thumbnail_img'] ?? null,
        ];
    }

    /**
     * The colour, choice and attribute JSON ProductService stores, and
     * whether the product has variant rows.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $variantNames
     * @param  list<string>  $colorCodes
     * @return array<string, mixed>
     */
    protected function variantColumns(array $data, array $variantNames, array $colorCodes): array
    {
        $choices = array_map(fn (array $choice): array => ['attribute_id' => (string) $choice['attribute_id'], 'values' => array_values($choice['values'])], $data['choice_options'] ?? []);

        return [
            'colors' => json_encode($colorCodes),
            'choice_options' => json_encode($choices, JSON_UNESCAPED_UNICODE),
            'attributes' => json_encode(array_column($choices, 'attribute_id')),
            'variant_product' => $variantNames === [] ? 0 : 1,
        ];
    }

    /** Keep the shared writer API while generated slugs obey the edit field's limit. */
    protected function numberedSlug(string $slug, ?int $ignore): string
    {
        return ProductSlug::numbered($slug, $ignore);
    }

    /**
     * One stock row per variant, at its own price, SKU, quantity and image;
     * or, with no variants, one row with an empty variant at the unit price
     * and the posted quantity and SKU, as ProductStockService writes them.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $variantNames
     * @return list<array{variant: string, price: mixed, sku: mixed, qty: mixed, image: mixed}>
     */
    protected function stockRows(array $data, array $variantNames): array
    {
        return $variantNames === []
            ? [['variant' => '', 'price' => $data['unit_price'], 'sku' => $data['sku'] ?? null, 'qty' => $data['current_stock'], 'image' => null]]
            : array_values(array_map(fn (array $variant): array => [
                'variant' => (string) $variant['variant'],
                'price' => $variant['price'],
                'sku' => $variant['sku'] ?? null,
                'qty' => $variant['qty'],
                'image' => $variant['image'] ?? null,
            ], $data['variants']));
    }
}
