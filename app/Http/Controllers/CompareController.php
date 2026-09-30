<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CompareController extends Controller
{
    public function index(Request $request)
    {
        //dd($request->session()->get('compare'));
        $categories = Category::all();
        return view('shop.frontend.view_compare', compact('categories'));
    }

    //clears the session data for compare
    public function reset(Request $request)
    {
        $request->session()->forget('compare');
        return back();
    }

    /**
     * Store the compared product ids in the session, keeping the last three.
     *
     * This app serializes sessions as JSON (config/session.php), so the list
     * comes back as a plain array on the next request rather than the
     * Collection the legacy code put there, and changing a copy of it no
     * longer changes what is stored. It is rebuilt as a Collection on read
     * and written back as an array.
     */
    public function addToCompare(Request $request)
    {
        $compare = collect($request->session()->get('compare', []));

        if (! $compare->contains($request->id)) {
            if ($compare->count() == 3) {
                $compare->shift();
            }

            $compare->push($request->id);
        }

        $request->session()->put('compare', $compare->values()->all());

        return view('shop.frontend.partials.compare');
    }
}
