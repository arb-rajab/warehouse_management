<?php

namespace App\Http\Resources\V1;

use App\Models\Brand;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BrandCollection extends ResourceCollection
{
    public function toArray($request)
    {
        // One query for the whole page, not one per brand.
        $categoryIds = Brand::categoryIdsFor($this->collection->pluck('id')->all());

        return [
            'data' => $this->collection->map(function($data) use ($categoryIds) {
                return [
                    'id' => $data->id,
                    'name' => $data->getTranslation('name'),
                    'logo' => uploaded_asset($data->logo),
                    // The categories where the brand has at least one product the shopper can see.
                    'category_ids' => $categoryIds[$data->id] ?? [],
                    'links' => [
                        'products' => route('api.products.brand', $data->id),
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
