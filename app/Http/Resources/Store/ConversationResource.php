<?php

namespace App\Http\Resources\Store;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read Conversation $resource */
class ConversationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $conversation = $this->resource;

        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'sender' => $conversation->sender === null ? null : ['id' => $conversation->sender->id, 'name' => $conversation->sender->name],
            'receiver' => $conversation->receiver === null ? null : ['id' => $conversation->receiver->id, 'name' => $conversation->receiver->name],
            'sender_viewed' => (bool) $conversation->sender_viewed,
            'receiver_viewed' => (bool) $conversation->receiver_viewed,
            'created_at' => $conversation->created_at?->toIso8601String(),
        ];
    }
}
