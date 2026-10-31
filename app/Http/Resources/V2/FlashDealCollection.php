<?php

namespace App\Http\Resources\V2;

use App\Http\Resources\ProductCollection;
use App\Models\FlashDeal;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class FlashDealCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function($data) {
                return [
                    'id' => $data->id,
                    'title' => $data->title,
                    'date' => (int) $data->end_date,
                    'banner' => uploaded_asset($data->banner),
                    'products' => new FlashDealProductCollection($data->flash_deal_products()->withListedProduct()->take(6)->get()),
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
