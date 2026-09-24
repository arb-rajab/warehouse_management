<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\Request;
use Carbon\Carbon;

class VisitController extends Controller
{
    public function index(Request $request){

        $visits = Visit::query();
        $date = $request->date;

        if ($date != null) {
            $orders = $visits->where('visit_date', '>=', date('Y-m-d', strtotime(explode(" to ", $date)[0])) . '  00:00:00')
                ->where('visit_date', '<=', date('Y-m-d', strtotime(explode(" to ", $date)[1])) . '  23:59:59');
        }

        if ($request->has('search')){
            $sort_search = $request->search;
            $visits->where(function ($q) use ($sort_search){
                $q->whereHas('rep', function ($item) use ($sort_search){
                    $item->where('name', 'like', '%' . $sort_search . '%');
                })
                ->orWhereHas('customer', function ($item) use ($sort_search){
                    $item->where('name', 'like', '%' . $sort_search . '%');
                });
            });
        }

        $visits = $visits
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('backend.representatives.visits.index', compact('visits', 'date'));

    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show($id)
    {
        $visit = Visit::findOrFail($id);
        return view('backend.representatives.visits.show', compact('visit'));
    }

    public function show_all_visits_in_a_day(Request $request, $rep_id)
    {
        $rep = User::findOrFail($rep_id);

        if(!$request->has('date')){
            // Fetch the first visit date for the representative or default to current date
            $date = optional($rep->rep_visits->first())->visit_date ?? now()->format('Y-m-d');
        } else{
            $date = $request->date;
        }

        // Convert the date to 'Y-m-d' format
        $date = Carbon::parse($date)->format('Y-m-d');

        // Filter visits for the selected date
        $visits = $rep->rep_visits()
                    ->whereDate('visit_date', $date)
                    ->orderByDesc('rank')
                    ->get();

        return view('backend.representatives.visits.rep-show', compact('visits', 'date', 'rep'));
    }

    public function destroy($id)
    {
        $visit = Visit::findOrFail($id);
        Visit::destroy($id);

        flash(translate('Visit has been deleted successfully'))->success();
        return redirect()->back();
    }

    public function bulk_visit_delete(Request $request) {
        if($request->id) {
            foreach ($request->id as $visit_id) {
                $this->destroy($visit_id);
            }
        }
        return 1;
    }


    public function update_note(Request $request)
    {
        $visit = Visit::find($request->visit_id);
        if($visit){
            $visit->note = $request->note;
            $visit->save();
            return 1;
        }
        return 0;
    }
}
