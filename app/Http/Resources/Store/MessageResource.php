<?php

namespace App\Http\Resources\Store;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read Message $resource */
class MessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $message = $this->resource;

        return [
            'id' => $message->id,
            'message' => $message->message,
            'user' => $message->user === null ? null : ['id' => $message->user->id, 'name' => $message->user->name],
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
