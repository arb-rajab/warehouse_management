<?php

namespace App\Http\Resources\V2\Warehouse;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
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
            'id'                        =>  $this->id,
            'code'                      =>  $this->code,
            'combined_order_id'         =>  $this->combined_order_id,
            'customer_id'               =>  $this->user_id,
            'member_serial'             =>  $this->member_serial,
            'delivery_status'           =>  $this->delivery_status,
            'payment_type'              =>  $this->payment_type,
            'payment_status'            =>  $this->payment_status,
            'order_from'                =>  $this->order_from,
            'grand_total'               =>  $this->grand_total,
            'system_shipping_address'   =>  $this->shipping_address,
            'company_address'           =>  $this->company_address,
            'company_shipping_address'  =>  $this->company_shipping_address,
            'company_tax_number'        =>  $this->company_tax_number,
            'customer_notes'            =>  $this->additional_info,
            'delivery_date'             =>  $this->delivery_date,
            'manager_notes'             =>  $this->manager_notes,
            'by_rep'                    =>  $this->by_rep,
            'rep_serial'                =>  $this->rep_serial,
            'for_customer'              =>  $this->for_customer,
            'discount_percent'          =>  $this->discount_percent,
            'indicator'                 =>  $this->indicator,
            'created_at'                =>  $this->created_at
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
