<?php

use App\Mail\SupportMailManager;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\Upload;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

function detailTicket(): Ticket
{
    return Ticket::forceCreate(['code' => 123456, 'user_id' => createStoreUser()->id, 'subject' => 'Help', 'details' => '<p>Customer detail</p><script>detail-attack</script>', 'status' => 'pending', 'viewed' => 0, 'client_viewed' => 1]);
}
test('ticket details sanitize customer HTML and paginate only the ticket replies and attachments', function () {
    $ticket = detailTicket();
    $noise = detailTicket();
    $upload = Upload::factory()->create(['user_id' => $ticket->user_id, 'file_name' => 'uploads/all/detail.png', 'file_original_name' => 'detail', 'extension' => 'png']);
    $ticket->files = (string) $upload->id;
    $ticket->save();
    $reply = TicketReply::forceCreate(['ticket_id' => $ticket->id, 'user_id' => $ticket->user_id, 'reply' => '<b>Keep reply</b><img src="x" onerror="reply-attack">', 'files' => (string) $upload->id]);
    TicketReply::forceCreate(['ticket_id' => $noise->id, 'user_id' => $noise->user_id, 'reply' => 'private-noise']);
    actingAsStoreUser(createStoreStaff('view_all_support_tickets'));
    $this->get(route('support_ticket.admin_show', encrypt($ticket->id)))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Support/Tickets/Show')->where('ticket.details_html', '<p>Customer detail</p>')->where('ticket.attachments', [['id' => $upload->id, 'name' => 'detail.png', 'url' => uploaded_asset($upload->id)]])->has('replies.data', 1)->has('replies.data.0', fn (Assert $row) => $row->where('id', $reply->id)->where('author', $ticket->user->name)->where('reply_html', '<b>Keep reply</b><img src="x">')->where('created_at', $reply->created_at->toIso8601String())->where('attachments', [['id' => $upload->id, 'name' => 'detail.png', 'url' => uploaded_asset($upload->id)]])));
    expect($ticket->fresh()->viewed)->toBe(1)->and($noise->fresh()->viewed)->toBe(0);
    for ($i = 0; $i < 22; $i++) {
        TicketReply::forceCreate(['ticket_id' => $ticket->id, 'user_id' => $ticket->user_id, 'reply' => 'Reply '.$i]);
    }
    $this->get(route('support_ticket.admin_show', encrypt($ticket->id)))->assertInertia(fn (Assert $page) => $page->has('replies.data', 20)->where('replies.meta.total', 23));
    $this->get(route('support_ticket.admin_show', ['supportTicket' => encrypt($ticket->id), 'page' => 2, 'per_page' => 10]))->assertInertia(fn (Assert $page) => $page->has('replies.data', 10)->where('filters.per_page', 10));
    $this->get(route('support_ticket.admin_show', ['supportTicket' => encrypt($ticket->id), 'per_page' => 7]))->assertSessionHasErrors('per_page');
});
test('ticket replies sanitize before persistence preserve owner actor flags status attachments and email behavior', function () {
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
    $ticket = detailTicket();
    $other = Upload::factory()->create(['user_id' => createStoreUser()->id]);
    actingAsStoreUser(createStoreStaff('reply_to_support_tickets'));
    $this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'reply' => '<script>attack</script>', 'status' => 'invalid', 'attachments' => (string) $other->id])->assertSessionHasErrors(['reply', 'status', 'attachments']);
    expect(TicketReply::count())->toBe(0)->and($ticket->fresh()->status)->toBe('pending');
    $this->post(route('support_ticket.admin_store'), ['ticket_id' => 999999, 'reply' => 'Keep', 'status' => 'open', 'attachments' => '999999'])->assertSessionHasErrors(['ticket_id', 'attachments']);
});
test('missing or malformed ticket detail identifiers are 404', function () {
    actingAsStoreUser(createStoreStaff('view_all_support_tickets'));
    $this->get(route('support_ticket.admin_show', encrypt(999999)))->assertNotFound();
    $this->get(route('support_ticket.admin_show', 'invalid'))->assertNotFound();
    $this->get(route('support_ticket.admin_show', encrypt([1, 2])))->assertNotFound();
});
test('ticket details and replies refuse unauthorized callers without read flags or writes', function (string $caller) {
    $ticket = detailTicket();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('support_ticket.admin_show', encrypt($ticket->id))), $caller);
    assertStoreCallerRefused($this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'reply' => 'forged', 'status' => 'solved']), $caller);
    expect($ticket->fresh()->viewed)->toBe(0)->and($ticket->fresh()->status)->toBe('pending')->and(TicketReply::count())->toBe(0);
})->with('refused store admin callers');
