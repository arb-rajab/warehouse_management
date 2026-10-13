<?php

namespace App\Http\Resources\Store;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The ticket on the admin ticket page: its request, cleaned, and its files.
 * `attachments` is set by the controller from App\Services\Store\TicketAttachments.
 *
 * @property-read Ticket $resource
 */
class SupportTicketDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $ticket = $this->resource;

        return [
            'id' => $ticket->id,
            'code' => (string) $ticket->code,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'user' => $ticket->user === null ? null : ['id' => $ticket->user->id, 'name' => $ticket->user->name],
            'created_at' => $ticket->created_at->toIso8601String(),
            'details_html' => clean_html($ticket->details),
            'attachments' => $ticket->getAttribute('attachments'),
        ];
    }
}
