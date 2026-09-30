<?php

namespace App\Http\Resources\V2;

use App\Models\ProductVariation;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function($data) {

                $productVariation = ProductVariation::where('product_id', $data->id)->get();

                return [
                    'id' => $data->id,
                    'name' => $data->getTranslation('name'),
                    'photos' => explode(',', $data->photos),
                    'thumbnail_image' => uploaded_asset($data->thumbnail_img),
                    'base_price' => (float) home_base_price($data, false),
                    'base_discounted_price' => (float) home_discounted_base_price($data, false),
                    'todays_deal' => (int) $data->todays_deal,
                    'featured' =>(int) $data->featured,
                    'unit' => $data->unit,
                    'serial' => $data->serial,
                    'mat_id' => $data->mat_id,
                    'discount' => (float) $data->discount,
                    'discount_type' => $data->discount_type,
                    'rating' => (float) $data->rating,
                    'sales' => (int) $data->num_of_sale,
                    'variation' => $productVariation,
                    'links' => [
                        'details' => route('products.show', $data->id),
                        'reviews' => route('api.reviews.index', $data->id),
                        'related' => route('products.related', $data->id),
                        'top_from_seller' => route('products.topFromSeller', $data->id),
                    ],
                ];
            }),
        ];
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200,
        ];
    }
}
