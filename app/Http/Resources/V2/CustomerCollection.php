<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CustomerCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function($data) {
                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'email' => $data->email,
                    'company_name' => $data->company_name,
                    'otajerID'     => $data->AccSysID,
                    'company_address' => $data->company_address,
                    'shipping_address' => $data->shipping_address,
                    'avatar' => uploaded_asset($data->avatar),
                    'address' => $data->address??"",
                    'country' => $data->country??"",
                    'state' => $data->state??"",
                    'city' => $data->city??"",
                    'postal_code' => $data->postal_code??"",
                    'phone' =>$data->phone??"",
                    'balance' =>single_price($data->balance),
                    'remaining_uploads' => $data->remaining_uploads,
                    'package_id' => $data->customer_package_id??"",
                    'package_name' => $data->customer_package->name??"",
                    'is_important' => $data->is_important,
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
