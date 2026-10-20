<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\BulkDeleteVisitsRequest;
use App\Http\Requests\Store\Admin\FilterVisitsRequest;
use App\Http\Requests\Store\Admin\ShowRepresentativeVisitsRequest;
use App\Http\Requests\Store\UpdateRepresentativeVisitNoteRequest;
use App\Http\Resources\Store\VisitDetailResource;
use App\Http\Resources\Store\VisitResource;
use App\Models\StoreUser;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
            new Middleware('permission:view_representative_visits', only: ['index', 'show', 'show_all_visits_in_a_day']),
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

    public function show(Request $request, Visit $visit): Response
    {
        $visit->load([
            'rep:id,name',
            'customer:id,name,company_name,email,phone,member_serial,AccSysID',
            'order:id,for_customer,delivery_status,code,date,grand_total,payment_type,additional_info,by_rep,delivery_date',
            'order.rep:id,AccSysID',
        ]);

        return Inertia::render('Store/Admin/Representatives/Visits/Show', [
            'visit' => new VisitDetailResource($visit),
            'mapMarkers' => $this->mapMarkers(new Collection([$visit])),
            'canUpdateNote' => $visit->rep_id === $request->user('store')->id,
        ]);
    }

    public function show_all_visits_in_a_day(ShowRepresentativeVisitsRequest $request, StoreUser $user): Response
    {
        $rep = $user;
        abort_unless($rep->is_rep, 404);
        $date = $request->has('date')
            ? $request->validated('date')
            : Visit::query()->where('rep_id', $rep->id)->orderBy('id')->value('visit_date');
        $date = Carbon::parse($date ?? now())->format('Y-m-d');
        $visits = Visit::query()
            ->select(['id', 'rep_id', 'customer_id', 'order_id', 'map_lat', 'map_lng', 'rank'])
            ->where('rep_id', $rep->id)
            ->with('customer:id,name')
            ->whereDate('visit_date', $date)
            ->orderByDesc('rank')
            ->orderBy('id')
            ->get();

        return Inertia::render('Store/Admin/Representatives/Visits/RepShow', [
            'rep' => ['id' => $rep->id, 'name' => $rep->name],
            'date' => $date,
            'visitCount' => $visits->count(),
            'mapMarkers' => $this->mapMarkers($visits),
        ]);
    }

    /**
     * Both the retained customer writer and its admin alias keep the same
     * ownership restriction (§7). A foreign visit remains a no-op, including
     * for Super Admin; only the response changes to a redirect back.
     */
    public function update_note(UpdateRepresentativeVisitNoteRequest $request, ?Visit $visit = null): RedirectResponse
    {
        $visit ??= Visit::query()->select(['id', 'rep_id', 'note'])->findOrFail((int) $request->validated('visit_id'));
        if ($visit->rep_id === $request->user('store')->id) {
            $visit->note = $request->validated('note');
            $visit->save();
        }

        return redirect()->back();
    }

    /**
     * @param  Collection<int, Visit>  $visits
     * @return list<array{id: int, lat: float, lng: float, rank: int, has_order: bool, label: string}>
     */
    private function mapMarkers(Collection $visits): array
    {
        $markers = [];
        foreach ($visits as $visit) {
            if (! is_numeric($visit->map_lat) || ! is_numeric($visit->map_lng)) {
                continue;
            }

            $latitude = (float) $visit->map_lat;
            $longitude = (float) $visit->map_lng;
            if (! is_finite($latitude) || ! is_finite($longitude) || abs($latitude) > 90 || abs($longitude) > 180) {
                continue;
            }

            $markers[] = [
                'id' => $visit->id,
                'lat' => $latitude,
                'lng' => $longitude,
                'rank' => $visit->rank + 1,
                'has_order' => $visit->order_id !== null,
                'label' => $visit->customer->name ?? '',
            ];
        }

        return $markers;
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
