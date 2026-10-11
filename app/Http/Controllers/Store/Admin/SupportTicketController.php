<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterSupportTicketsRequest;
use App\Http\Requests\Store\Admin\ShowSupportTicketRequest;
use App\Http\Resources\Store\SupportTicketResource;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:view_all_support_tickets', only: ['index', 'show'])];
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

    public function show(ShowSupportTicketRequest $request, Ticket $supportTicket): View
    {
        return app(\App\Http\Controllers\SupportTicketController::class)->admin_show(encrypt($supportTicket->id));
    }
}
