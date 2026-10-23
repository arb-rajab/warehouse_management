<?php

namespace App\Http\Resources\Store;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string|null $slug
 * @property-read float $rating
 *
 * @mixin Product
 */
class DashboardProductResource extends JsonResource
{
    /** @return array{id: int, name: string, public_url: string|null, thumbnail_url: string, base_price: string, discounted_price: string, rating: float} */
    public function toArray(Request $request): array
    {
        $upload = $this->thumbnailUpload;
        $url = $upload === null ? null : ($upload->external_link == null ? my_asset($upload->file_name) : ($upload->file_name ?? $upload->external_link));

        return [
            'id' => $this->id,
            'name' => (string) $this->resource->getTranslation('name'),
            'public_url' => $this->slug === null || $this->slug === '' ? null : route('product', $this->slug),
            'thumbnail_url' => $url !== null && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) ? $url : static_asset('assets/img/placeholder.jpg'),
            'base_price' => home_base_price($this->resource),
            'discounted_price' => home_discounted_base_price($this->resource),
            'rating' => (float) $this->rating,
        ];
    }
}
