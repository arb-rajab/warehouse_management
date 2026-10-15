<?php

namespace App\Http\Resources\Store;

use App\Models\TicketReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One reply on the admin ticket page. The body is cleaned here, so the page
 * only ever renders sanitised HTML; `attachments` is set by the controller
 * from App\Services\Store\TicketAttachments.
 *
 * @property-read TicketReply $resource
 */
class TicketReplyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $reply = $this->resource;

        return [
            'id' => $reply->id,
            'user' => $reply->user === null ? null : ['id' => $reply->user->id, 'name' => $reply->user->name],
            'reply_html' => clean_html($reply->reply),
            'created_at' => $reply->created_at->toIso8601String(),
            'attachments' => $reply->getAttribute('attachments'),
        ];
    }
}
