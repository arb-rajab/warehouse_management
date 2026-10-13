<?php

use App\Models\Ticket;
use App\Models\TicketReply;

test('a ticket belongs to its customer and keeps its ordered replies separate', function () {
    $customer = createStoreUser();
    $noiseCustomer = createStoreUser();
    $ticket = Ticket::forceCreate(['code' => 123456, 'user_id' => $customer->id, 'subject' => 'Help', 'details' => 'Help']);
    $other = Ticket::forceCreate(['code' => 123457, 'user_id' => $noiseCustomer->id, 'subject' => 'Other', 'details' => 'Other']);
    $older = TicketReply::forceCreate(['ticket_id' => $ticket->id, 'user_id' => $customer->id, 'reply' => 'Older', 'created_at' => '2026-10-09 10:00:00']);
    $newer = TicketReply::forceCreate(['ticket_id' => $ticket->id, 'user_id' => $customer->id, 'reply' => 'Newer', 'created_at' => '2026-10-09 11:00:00']);
    TicketReply::forceCreate(['ticket_id' => $other->id, 'user_id' => $noiseCustomer->id, 'reply' => 'Noise']);
    expect($ticket->user->id)->toBe($customer->id)->and($ticket->ticketreplies->modelKeys())->toBe([$newer->id, $older->id]);
});
