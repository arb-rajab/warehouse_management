<?php

use App\Http\Middleware\BlockMaliciousRequests;
use App\Mail\SupportMailManager;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\Upload;
use Illuminate\Support\Facades\Mail;

function detailTicket(): Ticket
{
    return Ticket::forceCreate(['code' => 123456, 'user_id' => createStoreUser()->id, 'subject' => 'Help', 'details' => '<p>Customer detail</p><script>detail-attack</script>', 'status' => 'pending', 'viewed' => 0, 'client_viewed' => 1]);
}
test('ticket replies sanitize before persistence preserve owner actor flags status attachments and email behavior', function () {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    Mail::fake();
    $ticket = detailTicket();
    $noise = detailTicket();
    $staff = actingAsStoreUser(createStoreStaff('reply_to_support_tickets'));
    $upload = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'user_id' => $ticket->user_id, 'reply' => '<p>Keep</p><script>attack</script>', 'attachments' => (string) $upload->id, 'status' => 'solved'])->assertRedirect();
    $reply = TicketReply::first();
    expect($reply->user_id)->toBe($staff->id)->and($reply->ticket_id)->toBe($ticket->id)->and($reply->reply)->toBe('<p>Keep</p>')->and($reply->files)->toBe((string) $upload->id)->and($ticket->fresh()->user_id)->toBe($ticket->user_id)->and($ticket->fresh()->status)->toBe('solved')->and($ticket->fresh()->client_viewed)->toBe(0)->and($noise->fresh()->client_viewed)->toBe(1);
    Mail::assertQueued(SupportMailManager::class);
});
test('ticket replies refuse invalid status empty unsafe HTML and inaccessible attachments atomically', function () {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    $ticket = detailTicket();
    $other = Upload::factory()->create(['user_id' => createStoreUser()->id]);
    actingAsStoreUser(createStoreStaff('reply_to_support_tickets'));
    $this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'reply' => '<script>attack</script>', 'status' => 'invalid', 'attachments' => (string) $other->id])->assertSessionHasErrors(['reply', 'status', 'attachments']);
    expect(TicketReply::count())->toBe(0)->and($ticket->fresh()->status)->toBe('pending');
    $this->post(route('support_ticket.admin_store'), ['ticket_id' => 999999, 'reply' => 'Keep', 'status' => 'open', 'attachments' => '999999'])->assertSessionHasErrors(['ticket_id', 'attachments']);
});

test('the established WAF refuses executable ticket content before any write', function () {
    $ticket = detailTicket();
    actingAsStoreUser(createStoreStaff('reply_to_support_tickets'));
    $this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'reply' => '<script>attack</script>', 'status' => 'solved'])->assertForbidden();
    expect(TicketReply::count())->toBe(0)->and($ticket->fresh()->status)->toBe('pending');
});
