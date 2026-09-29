<?php

namespace App\Http\Resources\V2\Seller;

use App\Http\Resources\V2\UploadedFileCollection;
use App\Models\Upload;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class StockCollection extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {


        return [
            "data" => $this->collection->map(function ($data) {
                return [
                    "id" => (int) $data->id,
                    "product_id" => $data->product_id,
                    "variant" => $data->variant,
                    "sku" => $data->sku,
                    "price" => $data->price,
                    "qty" => $data->qty,
                    "image" =>new UploadedFileCollection(Upload::where("id",$data->image)->get()),
                ];
            }),
        ];
    }
}
