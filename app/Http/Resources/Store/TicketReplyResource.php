<?php

namespace App\Http\Resources\Store;

use App\Models\TicketReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read TicketReply $resource */
class TicketReplyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $reply = $this->resource;

        return ['id' => $reply->id, 'author' => $reply->user?->name, 'reply_html' => clean_html($reply->reply), 'created_at' => $reply->created_at->toIso8601String(), 'attachments' => $reply->getAttribute('attachments')];
    }
}
