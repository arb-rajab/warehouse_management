<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\BulkDeleteVisitsRequest;
use App\Http\Requests\Store\Admin\FilterVisitsRequest;
use App\Http\Resources\Store\VisitResource;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class VisitController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_representative_visits', only: ['index']),
            new Middleware('permission:delete_representative_visit', only: ['destroy', 'bulkDestroy']),
        ];
    }

    public function index(FilterVisitsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $visits = Visit::query()->select(['id', 'rep_id', 'customer_id', 'order_id', 'note', 'visit_date'])->with(['rep:id,name', 'customer:id,name'])->withExists('order');
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $visits->where(fn (Builder $query) => $query
                ->whereHas('rep', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
                ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%')));
        }
        if ($request->filled('date_from')) {
            $visits->where('visit_date', '>=', $request->validated('date_from').' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $visits->where('visit_date', '<=', $request->validated('date_to').' 23:59:59');
        }

        return Inertia::render('Store/Admin/Representatives/Visits/Index', [
            'visits' => $this->paginated(VisitResource::collection($visits->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString())),
            'filters' => ['per_page' => $perPage, 'search' => $request->input('search', ''), 'date_from' => $request->input('date_from', ''), 'date_to' => $request->input('date_to', '')],
        ]);
    }

    public function destroy(Request $request, Visit $visit): RedirectResponse
    {
        $visit->delete();

        return $this->redirectPreservingQuery('admin.rep.visits.index', $request);
    }

    public function bulkDestroy(BulkDeleteVisitsRequest $request): RedirectResponse
    {
        Visit::query()->whereIn('id', $request->validated('id'))->delete();

        return $this->redirectPreservingQuery('admin.rep.visits.index', $request);
    }
}
