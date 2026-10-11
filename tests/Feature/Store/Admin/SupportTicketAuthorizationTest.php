<?php

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\Upload;
use Illuminate\Support\Facades\Mail;

test('a read-only ticket viewer cannot reply or change ticket status', function () {
    $ticket = Ticket::forceCreate(['code' => 123456, 'user_id' => createStoreUser()->id, 'subject' => 'Help', 'details' => 'Help', 'status' => 'pending', 'client_viewed' => 1]);
    actingAsStoreUser(createStoreStaff('view_all_support_tickets'));

    $this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'reply' => 'forged', 'status' => 'solved'])->assertForbidden();
    expect(TicketReply::count())->toBe(0)->and($ticket->fresh()->status)->toBe('pending')->and($ticket->fresh()->client_viewed)->toBe(1);
});

test('ticket replies refuse unauthorized callers without writes', function (string $caller) {
    $ticket = Ticket::forceCreate(['code' => 123456, 'user_id' => createStoreUser()->id, 'subject' => 'Help', 'details' => 'Help', 'status' => 'pending']);
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'reply' => 'forged', 'status' => 'solved']), $caller);
    expect(TicketReply::count())->toBe(0)->and($ticket->fresh()->status)->toBe('pending');
})->with('refused store admin callers');

test('the exact ticket reply permission preserves actor, status, and attachment IDs', function () {
    Mail::fake();
    $ticket = Ticket::forceCreate(['code' => 123456, 'user_id' => createStoreUser()->id, 'subject' => 'Help', 'details' => 'Help', 'status' => 'pending', 'client_viewed' => 1]);
    $noise = Ticket::forceCreate(['code' => 123457, 'user_id' => createStoreUser()->id, 'subject' => 'Other', 'details' => 'Other', 'status' => 'open']);
    $staff = actingAsStoreUser(createStoreStaff('reply_to_support_tickets'));
    $upload = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'reply' => 'Help sent', 'status' => 'solved', 'attachments' => (string) $upload->id, 'user_id' => $ticket->user_id])->assertRedirect();

    expect(TicketReply::query()->first()->only(['ticket_id', 'user_id', 'reply', 'files']))->toBe([
        'ticket_id' => $ticket->id, 'user_id' => $staff->id, 'reply' => 'Help sent', 'files' => (string) $upload->id,
    ])->and($ticket->fresh()->status)->toBe('solved')->and($ticket->fresh()->client_viewed)->toBe(0)
        ->and($noise->fresh()->status)->toBe('open');
});

test('ticket reads retain their gate and render sanitized details, replies and attachment links', function () {
    $customer = createStoreUser();
    $upload = Upload::factory()->create(['user_id' => $customer->id, 'file_name' => 'uploads/all/ticket.png', 'file_original_name' => 'ticket', 'extension' => 'png']);
    $ticket = Ticket::forceCreate(['code' => 123456, 'user_id' => $customer->id, 'subject' => 'Help', 'details' => '<p>Keep detail</p><script>alert("detail-xss")</script>', 'status' => 'pending', 'files' => (string) $upload->id]);
    TicketReply::forceCreate(['ticket_id' => $ticket->id, 'user_id' => $customer->id, 'reply' => '<b>Keep reply</b><img src=x onerror=alert("reply-xss")>', 'files' => (string) $upload->id]);
    actingAsStoreUser(createStoreStaff());
    $this->get(route('support_ticket.admin_show', encrypt($ticket->id)))->assertForbidden();
    actingAsStoreUser(createStoreStaff('view_all_support_tickets'));
    $this->get(route('support_ticket.admin_show', encrypt($ticket->id)))->assertOk()
        ->assertViewIs('shop.backend.support.support_tickets.show')->assertSee('Keep detail')->assertSee('Keep reply')->assertDontSee('detail-xss')->assertDontSee('reply-xss');

});
