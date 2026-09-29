<?php

namespace App\Http\Resources\V2\Warehouse;

use Illuminate\Http\Resources\Json\ResourceCollection;

class CustomerCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function($data) {
                return [
                    'id'                        =>  $data->id,
                    'member_serial'             =>  $data->member_serial,
                    'otajer_id'                 =>  $data->AccSysID,
                    'name'                      =>  $data->name,
                    'company_name'              =>  $data->company_name,
                    'email'                     =>  $data->email,
                    'country'                   =>  $data->country,
                    'phone'                     =>  $data->phone,
                    'admin_verified'            =>  $data->admin_verified ? true : false,
                    'tax_number'                =>  $data->tax_number,
                    'bank_account_number'       =>  $data->bank_account_number,
                    'company_address'           =>  $data->company_address,
                    'company_shipping_address'  =>  $data->shipping_address,
                    'is_rep'                    => $data->is_rep,
                    'rep_serial'                => $data->rep_serial,
                    'registered_at'             =>  $data->created_at->format('Y-m-d')
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
