<?php

namespace App\Http\Resources\V2;

use App\Models\StoreUser;
use App\Support\ApiResourceLabel;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PurchaseHistoryMiniCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function($data) {
                if($data->for_customer){ // if order by a rep.
                    $user = StoreUser::find($data->for_customer);
                    if($user){
                        $name = $user->name;
                        $AccSysID = $user->AccSysID; /// aka. Otajer ID
                    }
                    else{
                        $name = __('api_resources.account.user_not_found');
                        $AccSysID = null;
                    }
                }
                else{
                    $name = $data->user ? $data->user->name : __('api_resources.account.user_not_found');
                    $AccSysID = $data->user ? $data->user->AccSysID : null;
                }
                return [
                    'id' => $data->id,
                    'code' => $data->code,
                    'user_id' => intval($data->user_id),
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
