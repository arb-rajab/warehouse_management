<?php

namespace App\Http\Resources\V2;

use App\Models\Offer;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DynamicPopupCollection extends ResourceCollection
{
    protected function getBaseProductId($offer_id){
        $offer = Offer::find($offer_id);
        if (!$offer) {
            return [];
        }

        $base_product_id = $offer->base_product->product_id;
        return $base_product_id;

    }
    public function toArray($request)
    {

        return [
            'data' => $this->collection->map(function ($data) {
                return [
                    'id' => (integer)$data->id,
                    'name' => $data->title,
                    'summary' => $data->summary,
                    'banner' => uploaded_asset($data->banner),
                    'base_product_id' => $this->getBaseProductId($data->offer_id),
                    'delay_seconds' => $data->delay_seconds,
                    'btn_text' => $data->btn_text,
                    'show_page' => $data->show_page,
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
