<?php

namespace App\Http\Resources\V2;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\ResourceCollection;

class WalletCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function($data) {
                return [
                    'amount' => format_price ($data->amount) ,
                    'payment_method' => ucwords(str_replace('_', ' ', $data->payment_method)),
                    'approval_string' => $data->offline_payment ? ($data->approval == 1 ? __('storefront.common.approved') : __('api_resources.payment.declined')) : __('api_resources.payment.not_applicable'),
                    'date' => Carbon::createFromTimestamp(strtotime($data->created_at))->format('d-m-Y'),
                ];
            }),
        ];
    }

    public function with($request)
    {
        return [
            'result' => true,
            'status' => 200,
        ];
    }
}
