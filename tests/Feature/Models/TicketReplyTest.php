<?php

use App\Models\Ticket;
use App\Models\TicketReply;

test('a ticket reply belongs to its own ticket and author', function () {
    $author = createStoreUser();
    $ticket = Ticket::forceCreate(['user_id' => $author->id, 'code' => 123456, 'subject' => 'Help', 'details' => 'Help']);
    $noise = Ticket::forceCreate(['user_id' => createStoreUser()->id, 'code' => 123457, 'subject' => 'Other', 'details' => 'Other']);
    $reply = TicketReply::forceCreate(['user_id' => $author->id, 'ticket_id' => $ticket->id, 'reply' => 'Answer']);
    TicketReply::forceCreate(['user_id' => $noise->user_id, 'ticket_id' => $noise->id, 'reply' => 'Noise']);
    expect($reply->user->id)->toBe($author->id)->and($reply->ticket->id)->toBe($ticket->id)->and($ticket->ticketreplies->modelKeys())->toBe([$reply->id]);
});
