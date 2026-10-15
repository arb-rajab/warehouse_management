<?php

namespace App\Http\Resources\Store;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The language-independent fields of the digital product edit form, as the
 * legacy Blade form printed them; the name and description of the edited
 * language are sent beside it. The discount range is the legacy picker's
 * `d-m-Y H:i:s to d-m-Y H:i:s` in the app timezone, shown only when both
 * ends are set.
 *
 * @property-read int $id
 * @property-read int|null $category_id
 * @property-read string|null $photos
 * @property-read int|null $thumbnail_img
 * @property-read string|null $tags
 * @property-read string|float $unit_price
 * @property-read string|float|null $purchase_price
 * @property-read string|float|null $discount
 * @property-read string|null $discount_type
 * @property-read int|null $discount_start_date
 * @property-read int|null $discount_end_date
 * @property-read string|null $meta_title
 * @property-read string|null $meta_description
 * @property-read string|null $meta_img
 * @property-read string|null $slug
 * @property-read string|null $file_name
 *
 * @mixin Product
 */
class DigitalProductFormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $start = $this->discount_start_date;
        $end = $this->discount_end_date;

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'photos' => $this->photos,
            'thumbnail_img' => $this->thumbnail_img === null ? null : (string) $this->thumbnail_img,
            'tags' => $this->tags,
            'unit_price' => (string) $this->unit_price,
            'purchase_price' => $this->purchase_price === null ? null : (string) $this->purchase_price,
            'discount' => $this->discount === null ? null : (string) $this->discount,
            'discount_type' => $this->discount_type,
            'date_range' => $start && $end ? date('d-m-Y H:i:s', (int) $start).' to '.date('d-m-Y H:i:s', (int) $end) : null,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_img' => $this->meta_img,
            'slug' => $this->slug,
            'file' => $this->file_name,
        ];
    }
}
