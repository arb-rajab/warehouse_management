<?php

namespace App\Http\Resources\Store;

use App\Models\Product;
use App\Support\StorefrontUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the digital products list: the columns the legacy Blade list
 * showed, with its thumbnail and price formatting, plus the public product
 * link and the encrypted id the legacy download route still takes.
 *
 * @property-read int $id
 * @property-read string|null $added_by
 * @property-read int|null $thumbnail_img
 * @property-read string|float $unit_price
 * @property-read int $todays_deal
 * @property-read bool $published
 * @property-read int $featured
 * @property-read string|null $slug
 *
 * @mixin Product
 */
class DigitalProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name'),
            'added_by' => $this->added_by,
            'thumbnail_url' => uploaded_asset($this->thumbnail_img),
            'unit_price' => number_format((float) $this->unit_price, 2),
            'todays_deal' => (bool) $this->todays_deal,
            'published' => (bool) $this->published,
            'featured' => (bool) $this->featured,
            'public_url' => StorefrontUrl::to($this->resource),
            'download_token' => encrypt($this->id),
        ];
    }
}
