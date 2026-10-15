<?php

namespace App\Http\Resources\Store;

use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read Ticket $resource */
class SupportTicketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $ticket = $this->resource;
        $lastReply = $ticket->getAttribute('ticketreplies_max_created_at');

        return [
            'id' => $ticket->id, 'code' => (string) $ticket->code,
            'subject' => $ticket->subject, 'status' => $ticket->status, 'viewed' => (bool) $ticket->viewed,
            'user' => $ticket->user === null ? null : ['id' => $ticket->user->id, 'name' => $ticket->user->name],
            'created_at' => $ticket->created_at->toIso8601String(),
            'last_reply_at' => is_string($lastReply) ? CarbonImmutable::parse($lastReply)->toIso8601String() : $ticket->created_at->toIso8601String(),
        ];
    }
}
