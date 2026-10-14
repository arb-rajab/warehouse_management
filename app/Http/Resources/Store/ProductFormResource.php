<?php

namespace App\Http\Resources\Store;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductVariation;
use App\Models\StoreProductBarcode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The language-independent fields of the physical product edit form, as the
 * legacy Blade form printed them; the name, unit and description of the
 * edited language and the variant state are sent beside it.
 *
 * As the Blade form showed them: the minimum quantity is at least 1, the
 * quantity and SKU are the first stock row's, the flat shipping cost is the
 * stored shipping cost, and the flash discount and its type start from the
 * product's discount. The discount range is the legacy picker's
 * `d-m-Y H:i:s to d-m-Y H:i:s` in the app timezone, shown only when both ends
 * are set. The flash deal is the product's deal only while that deal is one
 * the form lists (`flash_deal_id` beside the resource).
 *
 * @property-read int $id
 * @property-read string|null $added_by
 * @property-read int|null $category_id
 * @property-read int|null $brand_id
 * @property-read string|float|null $weight
 * @property-read int|null $max_qty
 * @property-read int|null $min_qty
 * @property-read string|null $tags
 * @property-read string|null $photos
 * @property-read string|null $old_photo
 * @property-read int|null $thumbnail_img
 * @property-read string|null $video_provider
 * @property-read string|null $video_link
 * @property-read string|null $pdf
 * @property-read string|float $unit_price
 * @property-read string|float|null $discount
 * @property-read string|null $discount_type
 * @property-read int|null $discount_start_date
 * @property-read int|null $discount_end_date
 * @property-read string|null $external_link
 * @property-read string|null $external_link_btn
 * @property-read string|null $meta_title
 * @property-read string|null $meta_description
 * @property-read string|null $meta_img
 * @property-read string|null $slug
 * @property-read string|null $shipping_type
 * @property-read string|float|null $shipping_cost
 * @property-read int|null $is_quantity_multiplied
 * @property-read int|null $est_shipping_days
 * @property-read int|null $low_stock_quantity
 * @property-read string|null $stock_visibility_state
 * @property-read int|null $cash_on_delivery
 * @property-read int|null $featured
 * @property-read int|null $todays_deal
 *
 * @mixin Product
 */
class ProductFormResource extends JsonResource
{
    /**
     * @param  Product  $resource
     */
    public function __construct($resource, private ?int $flashDealId = null)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $start = $this->discount_start_date;
        $end = $this->discount_end_date;
        /** @var ProductStock|null $stock */
        $stock = $this->stocks->first();

        return [
            'id' => $this->id,
            'added_by' => $this->added_by,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'weight' => $this->weight === null ? null : (string) $this->weight,
            'max_qty' => $this->max_qty === null ? null : (string) $this->max_qty,
            'min_qty' => (string) max(1, (int) $this->min_qty),
            'tags' => $this->tags,
            'photos' => $this->photos,
            'old_photo' => $this->old_photo,
            'thumbnail_img' => $this->thumbnail_img === null ? null : (string) $this->thumbnail_img,
            'video_provider' => $this->video_provider,
            'video_link' => $this->video_link,
            'pdf' => $this->pdf,
            'unit_price' => (string) $this->unit_price,
            'discount' => $this->discount === null ? null : (string) $this->discount,
            'discount_type' => $this->discount_type,
            'date_range' => $start && $end ? date('d-m-Y H:i:s', (int) $start).' to '.date('d-m-Y H:i:s', (int) $end) : null,
            'current_stock' => $stock === null ? '0' : (string) $stock->getAttribute('qty'),
            'sku' => $stock?->getAttribute('sku'),
            'external_link' => $this->external_link,
            'external_link_btn' => $this->external_link_btn,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_img' => $this->meta_img,
            'slug' => $this->slug,
            'shipping_type' => $this->shipping_type === 'flat_rate' ? 'flat_rate' : 'free',
            'flat_shipping_cost' => (string) ($this->shipping_cost ?? 0),
            'is_quantity_multiplied' => (bool) $this->is_quantity_multiplied,
            'est_shipping_days' => $this->est_shipping_days === null ? null : (string) $this->est_shipping_days,
            'low_stock_quantity' => $this->low_stock_quantity === null ? null : (string) $this->low_stock_quantity,
            'stock_visibility_state' => in_array($this->stock_visibility_state, ['quantity', 'text', 'hide'], true) ? $this->stock_visibility_state : 'quantity',
            'cash_on_delivery' => (bool) $this->cash_on_delivery,
            'featured' => (bool) $this->featured,
            'todays_deal' => (bool) $this->todays_deal,
            'flash_deal_id' => $this->flashDealId,
            'product_types' => $this->variations->map(fn (ProductVariation $variation): string => (string) $variation->getAttribute('name'))->values()->all(),
            'product_barcodes' => $this->storeBarcodes->map(fn (StoreProductBarcode $barcode): string => (string) $barcode->getAttribute('barcode'))->values()->all(),
        ];
    }
}
