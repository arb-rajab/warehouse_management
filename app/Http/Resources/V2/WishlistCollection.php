<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\ProductVariation;

class WishlistCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function($data) {
                $productVariation = ProductVariation::where('product_id', $data->product->id)->get();
                return [

                    'id' => (integer) $data->id,
                    'product' => [
                        'id' => $data->product->id,
                        'name' => $data->product->name,
                        'unit_equal' => $data->product->unit_equal,
                        'mat_id'    => $data->product->mat_id,
                        'tax'       => $data->product->tax,
                        'variation' => $productVariation,
                        'thumbnail_image' => uploaded_asset($data->product->thumbnail_img),
                        'base_price' => format_price(home_base_price($data->product, false)) ,
                        'rating' => (double) $data->product->rating,
                    ]
                ];
            })
        ];
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200
        ];
    }
}
