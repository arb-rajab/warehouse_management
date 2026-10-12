<?php

namespace App\Http\Resources\V2\Warehouse;

use App\Models\StoreUser;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // Order::representative() is a conditional relation that an eager load
        // can't evaluate (it branches on `by_rep` of a blank model), so the
        // reps of the whole page are fetched once here instead.
        $repSerials = StoreUser::whereIn('id', $this->collection->where('by_rep', true)->pluck('user_id')->unique()->all())
            ->pluck('rep_serial', 'id');

        return [
            'data' => $this->collection->map(function($data) use ($repSerials) {
                return [
                    'id'                        =>  $data->id,
                    'code'                      =>  $data->code,
                    'combined_order_id'         =>  $data->combined_order_id,
                    'customer_id'               =>  $data->user_id,
                    'member_serial'             =>  $data->member_serial,
                    'delivery_status'           =>  $data->delivery_status,
                    'payment_type'              =>  $data->payment_type,
                    'payment_status'            =>  $data->payment_status,
                    'order_from'                =>  $data->order_from,
                    'grand_total'               =>  $data->grand_total,
                    'system_shipping_address'   =>  $data->shipping_address,
                    'company_address'           =>  $data->company_address,
                    'company_shipping_address'  =>  $data->company_shipping_address,
                    'company_tax_number'        =>  $data->company_tax_number,
                    'customer_notes'            =>  $data->additional_info,
                    'manager_notes'             =>  $data->manager_notes,
                    'delivery_date'             =>  $data->delivery_date,
                    'by_rep'                    =>  $data->by_rep,
                    'rep_serial'                =>  $data->by_rep ? $repSerials->get($data->user_id) : null,
                    'for_customer'              =>  $data->for_customer,
                    'discount_percent'          =>  $data->discount_percent,
                    'indicator'                 =>  $data->indicator,
                    'created_at'                =>  $data->created_at,

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
