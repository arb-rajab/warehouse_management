<?php

namespace App\Http\Controllers;

use App\Models\StoreUser;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VisitController extends Controller
{
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
        return view('shop.backend.representatives.visits.show', compact('visit'));
    }

    public function show_all_visits_in_a_day(Request $request, $rep_id)
    {
        $rep = StoreUser::findOrFail($rep_id);

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

        return view('shop.backend.representatives.visits.rep-show', compact('visits', 'date', 'rep'));
    }

    public function update_note(Request $request)
    {
        $visit = Visit::where('rep_id', auth()->user()->id)->find($request->visit_id);
        if($visit){
            $visit->note = $request->note;
            $visit->save();
            return 1;
        }
        return 0;
    }
}
