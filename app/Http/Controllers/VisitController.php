<?php

namespace App\Http\Controllers;

use App\Http\Requests\Store\Admin\ShowRepresentativeVisitsRequest;
use App\Models\StoreUser;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use App\Http\Requests\Store\UpdateRepresentativeVisitNoteRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class VisitController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_representative_visits', only: ['show', 'show_all_visits_in_a_day']),
        ];
    }

    public function show(int $id): View
    {
        $visit = Visit::query()
            ->select(['id', 'rep_id', 'customer_id', 'order_id', 'note', 'map_lat', 'map_lng', 'visit_date', 'rank', 'created_at', 'updated_at'])
            ->with([
                'customer:id,name,company_name,email,phone,member_serial,AccSysID',
                'order:id,for_customer,delivery_status,code,date,grand_total,payment_type,additional_info,by_rep,delivery_date',
                'order.rep:id,AccSysID',
            ])->findOrFail($id);
        $mapMarkers = $this->mapMarkers(new Collection([$visit]));

        return view('shop.backend.representatives.visits.show', compact('visit', 'mapMarkers'));
    }

    public function show_all_visits_in_a_day(ShowRepresentativeVisitsRequest $request, int $id): View
    {
        $rep = StoreUser::query()->select(['id', 'name'])->where('is_rep', true)->findOrFail($id);

        if (! $request->has('date')) {
            // Fetch the first visit date for the representative or default to current date
            $date = $rep->rep_visits()->orderBy('id')->value('visit_date') ?? now()->format('Y-m-d');
        } else {
            $date = $request->date;
        }

        // Convert the date to 'Y-m-d' format
        $date = Carbon::parse($date)->format('Y-m-d');

        // Filter visits for the selected date
        $visits = $rep->rep_visits()
            ->select(['id', 'rep_id', 'customer_id', 'order_id', 'map_lat', 'map_lng', 'rank'])
            ->with('customer:id,name')
            ->whereDate('visit_date', $date)
            ->orderByDesc('rank')
            ->get();
        $mapMarkers = $this->mapMarkers($visits);

        return view('shop.backend.representatives.visits.rep-show', compact('visits', 'date', 'rep', 'mapMarkers'));
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
                'label' => $visit->customer?->name ?? '',
            ];
        }

        return $markers;
    }

    public function update_note(UpdateRepresentativeVisitNoteRequest $request): int
    {
        $visit = Visit::query()->where('rep_id', $request->user('store')->id)->find($request->validated('visit_id'));
        if ($visit === null) {
            return 0;
        }
        $visit->note = $request->validated('note');
        $visit->save();

        return 1;
    }
}
