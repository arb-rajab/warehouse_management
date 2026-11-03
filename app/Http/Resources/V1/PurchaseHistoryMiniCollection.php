<?php

namespace App\Http\Resources\V1;

use App\Support\ApiResourceLabel;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PurchaseHistoryMiniCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function($data) {
                // `user_id` is who placed the order (the representative, for a
                // representative's order); `customer_id` and `name` are who it
                // is for. For an ordinary order the two are the same account.
                $placedByRep = $data->for_customer !== null;
                $customer = $placedByRep ? $data->rep : $data->user;
                $name = $customer ? $customer->name : __('api_resources.account.user_not_found');
                $AccSysID = $customer ? $customer->AccSysID : null; /// aka. Otajer ID
                return [
                    'id' => $data->id,
                    'code' => $data->code,
                    'user_id' => intval($data->user_id),
                    'customer_id' => intval($placedByRep ? $data->for_customer : $data->user_id),
                    'placed_by_id' => intval($data->user_id),
                    'placed_by_name' => $data->user?->name,
                    'placed_by_rep' => $placedByRep,
                    'name' => $name,
                    'otajer_id' => $AccSysID,
                    'payment_type' => ucwords(str_replace('_', ' ', $data->payment_type)) ,
                    'payment_status' => $data->payment_status,
                    'payment_status_string' => ApiResourceLabel::status($data->payment_status),
                    'delivery_status' => $data->delivery_status,
                    'delivery_status_string' => ApiResourceLabel::deliveryStatus($data->delivery_status),
                    'grand_total' => format_price($data->grand_total) ,
                    'date' => Carbon::createFromTimestamp($data->date)->format('d-m-Y'),
                    'links' => [
                        'details' => '',
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
