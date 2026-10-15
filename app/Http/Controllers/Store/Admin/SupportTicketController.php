<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterSupportTicketsRequest;
use App\Http\Requests\Store\Admin\ReplySupportTicketRequest;
use App\Http\Requests\Store\Admin\ShowSupportTicketRequest;
use App\Http\Resources\Store\SupportTicketDetailResource;
use App\Http\Resources\Store\SupportTicketResource;
use App\Http\Resources\Store\TicketReplyResource;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\Store\TicketAttachments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:view_all_support_tickets', only: ['index', 'show']), new Middleware('permission:reply_to_support_tickets', only: ['reply'])];
    }

    public function index(FilterSupportTicketsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search');

        return Inertia::render('Store/Admin/Support/Tickets/Index', [
            'tickets' => $this->paginated(SupportTicketResource::collection(
                Ticket::query()->select(['id', 'user_id', 'code', 'subject', 'status', 'viewed', 'created_at'])
                    ->whereHas('user')->with('user:id,name')->withMax('ticketreplies', 'created_at')
                    ->when(is_string($search) && $search !== '', fn ($query) => $query->where('code', 'like', '%'.$search.'%'))
                    ->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['per_page' => $perPage, 'search' => $search],
        ]);
    }

    /**
     * Opening a ticket marks it seen by staff (`viewed`), as the Blade page
     * did; its status and the customer's `client_viewed` are unchanged.
     * Replies list newest first, the Blade order, a page at a time.
     */
    public function show(ShowSupportTicketRequest $request, Ticket $supportTicket, TicketAttachments $attachments): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $supportTicket->viewed = 1;
        $supportTicket->save();
        $supportTicket->load('user:id,name');
        $replies = $supportTicket->ticketreplies()
            ->select(['id', 'ticket_id', 'user_id', 'reply', 'files', 'created_at'])
            ->with('user:id,name')->orderByDesc('id')
            ->paginate($perPage)->withQueryString();
        $uploads = $attachments->read([
            $supportTicket->files,
            ...$replies->getCollection()->map(fn (TicketReply $reply): ?string => $reply->files)->values()->all(),
        ]);
        $supportTicket->setAttribute('attachments', $attachments->forFiles($supportTicket->files, $uploads));
        $replies->getCollection()->each(
            fn (TicketReply $reply) => $reply->setAttribute('attachments', $attachments->forFiles($reply->files, $uploads))
        );

        return Inertia::render('Store/Admin/Support/Tickets/Show', [
            'ticket' => new SupportTicketDetailResource($supportTicket),
            'replies' => $this->paginated(TicketReplyResource::collection($replies)),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function reply(ReplySupportTicketRequest $request): RedirectResponse
    {
        $ticket = Ticket::query()->whereHas('user')->findOrFail((int) $request->validated('ticket_id'));
        $reply = DB::transaction(function () use ($request, $ticket) {
            $reply = TicketReply::forceCreate(['ticket_id' => $ticket->id, 'user_id' => $request->user('store')->id, 'reply' => clean_html($request->validated('reply')), 'files' => $request->validated('attachments')]);
            $ticket->forceFill(['client_viewed' => 0, 'status' => $request->validated('status')])->save();

            return $reply;
        });
        app(\App\Http\Controllers\SupportTicketController::class)->send_support_reply_email_to_user($ticket, $reply);

        // The binding takes the plain id. An encrypt()ed one can contain a `/`,
        // which the route cannot match, so returning by it could 404.
        return redirect()->route('support_ticket.admin_show', ['supportTicket' => $ticket->id]);
    }
}
