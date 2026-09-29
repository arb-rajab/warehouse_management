<?php

namespace App\Http\Resources\V2\Warehouse;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'                        => $this->id,
            'member_serial'             => $this->member_serial,
            'otajer_id'                 => $this->AccSysID,
            'name'                      => $this->name,
            'company_name'              => $this->company_name,
            'email'                     => $this->email,
            'country'                   => $this->country,
            'phone'                     => $this->phone,
            'admin_verified'            => $this->admin_verified ? true : false,
            'tax_number'                => $this->tax_number,
            'bank_account_number'       => $this->bank_account_number,
            'company_address'           => $this->company_address,
            'company_shipping_address'  => $this->shipping_address,
            'is_rep'                    => $this->is_rep,
            'rep_serial'                => $this->rep_serial,
            'registered_at'             => $this->created_at->format('Y-m-d')
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
