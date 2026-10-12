<?php

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function adminSupportTicket(array $overrides = []): Ticket
{
    return Ticket::forceCreate(array_replace(['code' => 123456, 'user_id' => createStoreUser()->id, 'subject' => 'Help', 'details' => 'Private body', 'files' => '999', 'status' => 'pending', 'viewed' => 0], $overrides));
}

test('the ticket list sends a narrow record and the newest reply date without loading bodies', function () {
    actingAsStoreUser(createStoreStaff('view_all_support_tickets'));
    $ticket = adminSupportTicket(['created_at' => '2026-10-09 10:00:00']);
    TicketReply::forceCreate(['ticket_id' => $ticket->id, 'user_id' => $ticket->user_id, 'reply' => 'Private old reply', 'created_at' => '2026-10-09 11:00:00']);
    TicketReply::forceCreate(['ticket_id' => $ticket->id, 'user_id' => $ticket->user_id, 'reply' => 'Private new reply', 'created_at' => '2026-10-09 12:00:00']);
    $customer = $ticket->user;
    $this->get(route('support_ticket.admin_index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Support/Tickets/Index')->where('filters', ['per_page' => 20, 'search' => null])
        ->has('tickets.data', 1)->has('tickets.data.0', fn (Assert $record) => $record
        ->where('id', $ticket->id)
        ->where('code', '123456')->where('subject', 'Help')->where('status', 'pending')->where('viewed', false)
        ->where('user', ['id' => $customer->id, 'name' => $customer->name])
        ->where('created_at', '2026-10-09T10:00:00+00:00')->where('last_reply_at', '2026-10-09T12:00:00+00:00')));
});

test('ticket lists page, search codes and retain filters', function () {
    actingAsStoreUser(createStoreStaff('view_all_support_tickets'));
    $customer = createStoreUser();
    $ids = collect(range(1, 23))->map(fn ($n) => adminSupportTicket(['user_id' => $customer->id, 'code' => 123450 + $n])->id)->reverse()->values();
    $this->get(route('support_ticket.admin_index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('tickets.data', 20)->where('tickets.meta.total', 23)->where('tickets.data.0.id', $ids[0]));
    $this->get(route('support_ticket.admin_index', ['page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page->has('tickets.data', 3)->where('tickets.data.0.id', $ids[20]));
    $this->get(route('support_ticket.admin_index', ['per_page' => 10, 'search' => '12345']))->assertOk()->assertInertia(fn (Assert $page) => $page->has('tickets.data', 9)->where('filters', ['per_page' => 10, 'search' => '12345']));
    $this->get(route('support_ticket.admin_index', ['per_page' => 7]))->assertSessionHasErrors('per_page');
    $this->get(route('support_ticket.admin_index', ['search' => str_repeat('1', 101)]))->assertSessionHasErrors('search');
});

test('tickets without customers stay out and unreplied tickets fall back to their sending date', function () {
    actingAsStoreUser(createStoreStaff('view_all_support_tickets'));
    $ticket = adminSupportTicket();
    adminSupportTicket(['user_id' => 999999]);
    $this->get(route('support_ticket.admin_index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('tickets.data', 1)->where('tickets.meta.total', 1)->where('tickets.data.0.last_reply_at', $ticket->created_at->toIso8601String()));
});

test('ticket lists refuse unauthorized store callers', function (string $caller) {
    $ticket = adminSupportTicket();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route('support_ticket.admin_index')), $caller);
    expect($ticket->fresh()->viewed)->toBe(0);
})->with('refused store admin callers');

test('WMS sessions cannot open the ticket list', function () {
    $this->actingAs(User::factory()->create(), 'web');
    $this->get(route('support_ticket.admin_index'))->assertRedirect(route('shop.login'));
});

test('ticket detail keeps the Blade reply form and encrypted email links', function () {
    actingAsStoreUser(createStoreStaff('view_all_support_tickets'));
    $ticket = adminSupportTicket();
    foreach ([$ticket->id, encrypt($ticket->id)] as $id) {
        $this->get(route('support_ticket.admin_show', $id))->assertOk()->assertViewIs('shop.backend.support.support_tickets.show');
    }
    actingAsStoreUser(createStoreStaff());
    $this->get(route('support_ticket.admin_show', $ticket->id))->assertForbidden();
});
