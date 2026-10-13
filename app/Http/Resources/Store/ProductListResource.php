<?php

namespace App\Http\Resources\Store;

use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the in-house or all-products list: what the legacy Blade list
 * showed, formatted as it formatted it. Names are the synced `name` and
 * `ar_name` columns, prices go through `single_price()`, the thumbnail
 * through `uploaded_asset()` (placeholder included) and the category name
 * through its translation. Stock is the first stock row's quantity, or each
 * variant's for a variant product, with the legacy "low" comparison.
 *
 * @property-read int $id
 * @property-read string $name
 * @property-read string $ar_name
 * @property-read int|null $thumbnail_img
 * @property-read string|null $mat_id
 * @property-read string|null $api_unit_name
 * @property-read int|null $unit_equal
 * @property-read int $num_of_sale
 * @property-read string|float $unit_price
 * @property-read int $variant_product
 * @property-read int|null $low_stock_quantity
 * @property-read int|bool $for_sale
 * @property-read bool $published
 * @property-read int $featured
 * @property-read int|bool $deleted_from_api
 * @property-read string|null $slug
 *
 * @mixin Product
 */
class ProductListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $stocks = $this->stocks;
        $variantStocks = $this->variant_product
            ? $stocks->map(fn (ProductStock $stock) => ['variant' => (string) $stock->getAttribute('variant'), 'qty' => (int) $stock->getAttribute('qty')])->values()->all()
            : null;
        $quantity = $variantStocks === null ? $stocks->first()?->getAttribute('qty') : array_sum(array_column($variantStocks, 'qty'));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'ar_name' => $this->ar_name,
            'thumbnail_url' => uploaded_asset($this->thumbnail_img),
            'mat_id' => $this->mat_id,
            'unit' => $this->api_unit_name.'('.$this->unit_equal.')',
            'num_of_sale' => (int) $this->num_of_sale,
            'base_price' => single_price($this->unit_price),
            'profit' => single_price($this->resource->profit()),
            'variant_stocks' => $variantStocks,
            'stock_quantity' => $quantity === null ? null : (int) $quantity,
            // The legacy view's own loose comparison, kept for its null cases.
            'low_stock' => $quantity <= $this->low_stock_quantity,
            'category' => $this->category?->getTranslation('name'),
            'for_sale' => (bool) $this->for_sale,
            'published' => (bool) $this->published,
            'featured' => (bool) $this->featured,
            'deleted_from_api' => (bool) $this->deleted_from_api,
            'public_url' => $this->slug === null || $this->slug === '' ? null : route('product', $this->slug),
        ];
    }
}
