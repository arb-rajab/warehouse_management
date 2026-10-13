<?php

use App\Http\Middleware\BlockMaliciousRequests;
use App\Mail\SupportMailManager;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/** A customer's ticket as the storefront creates it. */
function adminDetailTicket(array $overrides = []): Ticket
{
    return Ticket::forceCreate(array_replace([
        'code' => 123456, 'user_id' => createStoreUser()->id, 'subject' => 'Late order',
        'details' => '<p>Customer <b>detail</b></p><script>alert("detail")</script>', 'files' => null,
        'status' => 'open', 'viewed' => 0, 'client_viewed' => 1,
    ], $overrides));
}

/** @return array<string, mixed> */
function ticketSnapshot(Ticket $ticket): array
{
    return [
        'ticket' => DB::table('tickets')->where('id', $ticket->id)->first(['status', 'viewed', 'client_viewed', 'updated_at']),
        'replies' => DB::table('ticket_replies')->orderBy('id')->get()->all(),
    ];
}

beforeEach(function () {
    Mail::fake();
});

test('the ticket detail screen can be rendered by its plain and its emailed encrypted id', function () {
    actingAsStoreUser(createStoreStaff('view_all_support_tickets'));
    $ticket = adminDetailTicket();
    do {
        $encryptedId = encrypt($ticket->id);
    } while (preg_match('#[/+]#', $encryptedId));

    foreach ([$ticket->id, $encryptedId] as $id) {
        $this->get(route('support_ticket.admin_show', $id))->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Store/Admin/Support/Tickets/Show')
        );
    }
});

test('the ticket detail sends the cleaned ticket, its replies and their attachments', function () {
    $staff = actingAsStoreUser(createStoreStaff('view_all_support_tickets'));
    $ticket = adminDetailTicket(['created_at' => '2026-10-09 10:00:00']);
    $customer = $ticket->user;
    $receipt = Upload::factory()->create(['user_id' => $customer->id, 'type' => 'image', 'file_original_name' => 'receipt', 'file_size' => 2048, 'external_link' => 'https://cdn.example/receipt.png']);
    $missing = Upload::factory()->stored('uploads/all/gone.png')->create(['user_id' => $customer->id, 'type' => 'image', 'file_original_name' => 'gone', 'file_size' => 10]);
    $ticket->forceFill(['files' => "{$receipt->id},999999,{$missing->id},{$receipt->id}"])->save();
    $answer = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image', 'file_original_name' => 'answer', 'file_size' => 512, 'external_link' => 'https://cdn.example/answer.png']);
    $old = TicketReply::forceCreate(['ticket_id' => $ticket->id, 'user_id' => $customer->id, 'reply' => '<i>Any news?</i>', 'created_at' => '2026-10-09 11:00:00']);
    $latest = TicketReply::forceCreate([
        'ticket_id' => $ticket->id, 'user_id' => $staff->id, 'files' => (string) $answer->id, 'created_at' => '2026-10-09 12:00:00',
        'reply' => '<p style="text-align: center"><strong>Shipped</strong> <a href="javascript:alert(1)">track</a><img src="x" onerror="alert(1)"></p>',
    ]);
    $noise = adminDetailTicket();
    TicketReply::forceCreate(['ticket_id' => $noise->id, 'user_id' => $noise->user_id, 'reply' => 'private-noise']);

    $this->get(route('support_ticket.admin_show', $ticket))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Support/Tickets/Show')
        ->where('filters', ['per_page' => 20])
        ->has('ticket', fn (Assert $record) => $record
            ->where('id', $ticket->id)->where('code', '123456')->where('subject', 'Late order')->where('status', 'open')
            ->where('user', ['id' => $customer->id, 'name' => $customer->name])
            ->where('created_at', '2026-10-09T10:00:00+00:00')
            ->where('details_html', '<p>Customer <b>detail</b></p>')
            ->where('attachments', [
                ['id' => $receipt->id, 'name' => 'receipt', 'type' => 'image', 'size' => 2048, 'url' => 'https://cdn.example/receipt.png'],
                ['id' => $missing->id, 'name' => 'gone', 'type' => 'image', 'size' => 10, 'url' => null],
            ]))
        ->where('replies.meta.total', 2)->has('replies.data', 2)
        ->has('replies.data.0', fn (Assert $record) => $record
            ->where('id', $latest->id)
            ->where('user', ['id' => $staff->id, 'name' => $staff->name])
            ->where('reply_html', '<p style="text-align: center"><strong>Shipped</strong> <a rel="noopener noreferrer nofollow">track</a><img src="x"></p>')
            ->where('created_at', '2026-10-09T12:00:00+00:00')
            ->where('attachments', [['id' => $answer->id, 'name' => 'answer', 'type' => 'image', 'size' => 512, 'url' => 'https://cdn.example/answer.png']]))
        ->where('replies.data.1.id', $old->id)
        ->where('replies.data.1.reply_html', '<i>Any news?</i>')
        ->where('replies.data.1.attachments', []));
});

test('a reply whose author account is gone still renders', function () {
    actingAsStoreUser(createStoreStaff('view_all_support_tickets'));
    $ticket = adminDetailTicket();
    TicketReply::forceCreate(['ticket_id' => $ticket->id, 'user_id' => 999999, 'reply' => '<p>Orphan</p>']);

    $this->get(route('support_ticket.admin_show', $ticket))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('replies.data.0', fn (Assert $record) => $record->where('user', null)
            ->where('reply_html', '<p>Orphan</p>')->where('attachments', [])->etc()));
});

test('ticket replies page newest first and validate the page size', function () {
    actingAsStoreUser(createStoreStaff('view_all_support_tickets'));
    $ticket = adminDetailTicket();
    $ids = collect(range(1, 23))->map(fn (int $n) => TicketReply::forceCreate([
        'ticket_id' => $ticket->id, 'user_id' => $ticket->user_id, 'reply' => "Reply {$n}", 'created_at' => now()->addMinutes($n),
    ])->id)->reverse()->values();

    $this->get(route('support_ticket.admin_show', $ticket))->assertInertia(fn (Assert $page) => $page
        ->has('replies.data', 20)->where('replies.meta.total', 23)->where('replies.data.0.id', $ids[0]));
    $this->get(route('support_ticket.admin_show', [$ticket, 'page' => 2]))->assertInertia(fn (Assert $page) => $page
        ->has('replies.data', 3)->where('replies.data.0.id', $ids[20]));
    $this->get(route('support_ticket.admin_show', [$ticket, 'per_page' => 10]))->assertInertia(fn (Assert $page) => $page
        ->has('replies.data', 10)->where('filters.per_page', 10));
    $this->get(route('support_ticket.admin_show', [$ticket, 'per_page' => 7]))->assertSessionHasErrors('per_page');
});

test('opening a ticket marks it seen by staff and changes nothing else', function () {
    actingAsStoreUser(createStoreStaff('view_all_support_tickets'));
    $ticket = adminDetailTicket(['status' => 'pending']);
    $noise = adminDetailTicket();

    $this->get(route('support_ticket.admin_show', $ticket))->assertOk();
    expect($ticket->fresh()->only(['viewed', 'client_viewed', 'status']))->toBe(['viewed' => 1, 'client_viewed' => 1, 'status' => 'pending'])
        ->and($noise->fresh()->viewed)->toBe(0)
        ->and(TicketReply::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('a ticket attachment stays listed for its viewer without opening it to library previews or replies', function () {
    $staff = actingAsStoreUser(createStoreStaff('view_all_support_tickets', 'reply_to_support_tickets'));
    $ticket = adminDetailTicket();
    $foreign = Upload::factory()->create(['user_id' => $ticket->user_id, 'type' => 'image', 'file_original_name' => 'customer-photo', 'external_link' => 'https://cdn.example/customer.png']);
    $ticket->forceFill(['files' => (string) $foreign->id])->save();

    $this->get(route('support_ticket.admin_show', $ticket))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('ticket.attachments.0.id', $foreign->id)->where('ticket.attachments.0.url', 'https://cdn.example/customer.png'));
    $this->getJson(route('uploaded-files.info', ['ids' => [$foreign->id]]))->assertForbidden();
    $before = ticketSnapshot($ticket);
    $this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'reply' => 'Reusing it', 'status' => 'open', 'attachments' => (string) $foreign->id])
        ->assertSessionHasErrors('attachments');
    expect(ticketSnapshot($ticket))->toEqual($before);

    $own = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'reply' => 'Mine', 'status' => 'open', 'attachments' => (string) $own->id])
        ->assertRedirect(route('support_ticket.admin_show', $ticket));
    expect(TicketReply::sole()->files)->toBe((string) $own->id);
});

test('a library manager may attach any image from the library', function () {
    actingAsStoreUser(createStoreStaff('reply_to_support_tickets', 'view_uploads'));
    $ticket = adminDetailTicket();
    $library = Upload::factory()->create(['user_id' => createStoreUser()->id, 'type' => 'image']);
    $document = Upload::factory()->create(['user_id' => createStoreUser()->id, 'type' => 'document']);

    $this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'reply' => 'Doc', 'status' => 'open', 'attachments' => (string) $document->id])
        ->assertSessionHasErrors('attachments');
    $this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'reply' => 'Library', 'status' => 'open', 'attachments' => (string) $library->id])
        ->assertRedirect(route('support_ticket.admin_show', $ticket));
    expect(TicketReply::sole()->files)->toBe((string) $library->id);
});

test('a reply keeps allowed formatting, strips scripts, handlers and javascript URLs and returns to the ticket', function () {
    $this->withoutMiddleware(BlockMaliciousRequests::class);
    $staff = actingAsStoreUser(createStoreStaff('view_all_support_tickets', 'reply_to_support_tickets'));
    $ticket = adminDetailTicket();

    $this->post(route('support_ticket.admin_store'), [
        'ticket_id' => $ticket->id, 'status' => 'solved',
        'reply' => '<h2>Done</h2><p style="text-align: center"><strong>Bold</strong> <em>it</em> <u>u</u> <s>s</s> <span style="color: rgb(255, 0, 0)">red</span></p>'
            .'<ul><li><p>One</p></li></ul><blockquote><p>Quote</p></blockquote><p><a href="https://example.com/track">Track</a></p>'
            .'<table><tbody><tr><td><p>A</p></td></tr></tbody></table>'
            .'<script>alert(1)</script><img src="https://example.com/p.png" onerror="alert(2)"><a href="javascript:alert(3)">bad</a>',
    ])->assertRedirect(route('support_ticket.admin_show', $ticket));

    expect(TicketReply::sole()->only(['ticket_id', 'user_id', 'reply']))->toBe([
        'ticket_id' => $ticket->id, 'user_id' => $staff->id,
        'reply' => '<h2>Done</h2><p style="text-align: center"><strong>Bold</strong> <em>it</em> <u>u</u> <s>s</s> <span style="color: rgb(255, 0, 0)">red</span></p>'
            .'<ul><li><p>One</p></li></ul><blockquote><p>Quote</p></blockquote><p><a href="https://example.com/track" rel="noopener noreferrer nofollow">Track</a></p>'
            .'<table><tbody><tr><td><p>A</p></td></tr></tbody></table>'
            .'<img src="https://example.com/p.png"><a rel="noopener noreferrer nofollow">bad</a>',
    ])->and($ticket->fresh()->only(['status', 'client_viewed', 'viewed']))->toBe(['status' => 'solved', 'client_viewed' => 0, 'viewed' => 0]);
    Mail::assertQueued(SupportMailManager::class, fn (SupportMailManager $mail) => $mail->hasTo($ticket->user->email));
});

test('the established WAF still refuses a raw script reply before any write', function () {
    actingAsStoreUser(createStoreStaff('reply_to_support_tickets'));
    $ticket = adminDetailTicket();
    $before = ticketSnapshot($ticket);
    $this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'reply' => '<p>Hi</p><script>alert(1)</script>', 'status' => 'solved'])->assertForbidden();
    expect(ticketSnapshot($ticket))->toEqual($before);
    Mail::assertNothingQueued();
});

test('missing, malformed and customerless ticket identifiers are 404', function () {
    actingAsStoreUser(createStoreStaff('view_all_support_tickets'));
    $orphan = adminDetailTicket(['user_id' => 999999]);
    $this->get(route('support_ticket.admin_show', 999999))->assertNotFound();
    $this->get(route('support_ticket.admin_show', 'not-a-ticket'))->assertNotFound();
    $this->get(route('support_ticket.admin_show', $orphan))->assertNotFound();
    expect($orphan->fresh()->viewed)->toBe(0);
});

test('ticket detail refuses unauthorized callers without marking the ticket seen', function (string $caller) {
    $ticket = adminDetailTicket();
    $before = ticketSnapshot($ticket);
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('support_ticket.admin_show', $ticket)), $caller);
    expect(ticketSnapshot($ticket))->toEqual($before);
})->with('refused store admin callers');

test('ticket replies refuse unauthorized callers without writes or mail', function (string $caller) {
    $ticket = adminDetailTicket();
    $before = ticketSnapshot($ticket);
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'reply' => 'forged', 'status' => 'solved']), $caller);
    expect(ticketSnapshot($ticket))->toEqual($before);
    Mail::assertNothingQueued();
})->with('refused store admin callers');

test('WMS sessions cannot open or reply to a ticket', function () {
    $ticket = adminDetailTicket();
    $before = ticketSnapshot($ticket);
    $this->actingAs(User::factory()->create(), 'web');
    $this->get(route('support_ticket.admin_show', $ticket))->assertRedirect(route('shop.login'));
    $this->post(route('support_ticket.admin_store'), ['ticket_id' => $ticket->id, 'reply' => 'forged', 'status' => 'solved'])->assertRedirect(route('shop.login'));
    expect(ticketSnapshot($ticket))->toEqual($before);
});

test('the customer ticket pages stay Blade, show staff replies cleaned and mark the ticket read by the customer', function () {
    $ticket = adminDetailTicket();
    $customer = $ticket->user;
    TicketReply::forceCreate(['ticket_id' => $ticket->id, 'user_id' => createStoreStaff()->id, 'reply' => '<p><strong>Staff answer</strong></p><img src=x onerror=alert("reply-xss")>']);
    DB::table('tickets')->where('id', $ticket->id)->update(['client_viewed' => 0]);
    $other = adminDetailTicket(['code' => 654321, 'subject' => 'Someone else']);
    actingAsStoreUser($customer);

    $this->get(route('support_ticket.index'))->assertOk()->assertViewIs('shop.frontend.user.support_ticket.index')
        ->assertSee('Late order')->assertDontSee('Someone else');
    $this->get(route('support_ticket.show', encrypt($ticket->id)))->assertOk()->assertViewIs('shop.frontend.user.support_ticket.show')
        ->assertSee('<strong>Staff answer</strong>', false)->assertDontSee('reply-xss');
    expect($ticket->fresh()->client_viewed)->toBe(1)->and($ticket->fresh()->viewed)->toBe(0);
    $this->get(route('support_ticket.show', encrypt($other->id)))->assertNotFound();
});
