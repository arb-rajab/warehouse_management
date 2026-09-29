<?php

namespace App\Http\Resources\V2\Seller;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ConversationCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($data) {
                $seen_status = false;
                if (
                    (auth()->user()->id == $data->sender_id && $data->sender_viewed == 0) ||
                    (auth()->user()->id == $data->receiver_id && $data->receiver_viewed == 0)
                ) {
                    $seen_status = true;
                }
                if (auth()->user()->id == $data->sender_id) {
                    $image = uploaded_asset($data->receiver->avatar_original);
                    $name = $data->receiver->name;
                } else {
                    $image = uploaded_asset($data->sender->avatar_original);
                    $name = $data->sender->name;
                }
                return [
                    'id'    => $data->id,
                    'image' => $image,
                    'name'  => $name,
                    'title' => $data->title,
                    'is_seen' => $seen_status,
                ];
            }),
        ];
    }
}
