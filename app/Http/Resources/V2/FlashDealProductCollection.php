<?php

namespace App\Http\Resources\V2;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class FlashDealProductCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Retrieve the products as a collection
        $products = $this->collection->map(function ($item) {
            return $item->product; // Ensure you return the correct product instance
        });

        // Wrap the collection in a new ProductMiniCollection
        return new ProductMiniCollection($products);
    }
}
