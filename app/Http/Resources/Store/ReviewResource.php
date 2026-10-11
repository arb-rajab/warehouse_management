<?php

namespace App\Http\Resources\Store;

use App\Models\Product;
use App\Models\StoreUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read int $rating
 * @property-read string $comment
 * @property-read int $status
 * @property-read Product $product
 * @property-read StoreUser $user
 */
class ReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'published' => (bool) $this->status,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->getTranslation('name'),
                'owner' => $this->product->getAttribute('added_by'),
                'url' => route('product', $this->product->getAttribute('slug')),
            ]),
            'customer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
        ];
    }
}
