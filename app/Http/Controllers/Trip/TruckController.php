<?php

namespace App\Http\Controllers\Trip;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Truck;
use Illuminate\Support\Facades\Validator;

class TruckController extends Controller
{
    public function index(Request $request)
    {
        $trucks = Truck::query();
        $sort_search = null;

        if ($request->has('search')){

            $sort_search = $request->search;

            $trucks->where(function ($q) use ($sort_search){
                $q->where('name', 'like', '%'.$sort_search.'%')
                    ->orWhere('license_plate', 'like', '%'.$sort_search.'%');
            });
        }

        $trucks = $trucks->paginate(15);
        return view('backend.trips.trucks.index', compact('trucks', 'sort_search'));
    }

    public function create()
    {
        return view('backend.trips.trucks.create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'model' => 'nullable|string|max:50',
            'max_pallets_number' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
            'license_plate' => 'nullable|string|max:50|unique:trucks,license_plate',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $truck = new Truck;
        $truck->name = $request->name;
        $truck->description = $request->description;
        $truck->model = $request->model;
        $truck->max_pallets_number = $request->max_pallets_number;
        $truck->license_plate = $request->license_plate;
        $truck->photos = $request->photos;
        $truck->save();

        flash('Truck Created Successfully')->success();
        return redirect()->route('trucks.index');
    }

    public function edit($id)
    {
        $truck = Truck::find($id);

        return view('backend.trips.trucks.edit', compact('truck'));
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'model' => 'nullable|string|max:50',
            'max_pallets_number' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
            'license_plate' => 'nullable|string|max:50|unique:trucks,license_plate,' . $request->id,
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $truck = Truck::find($request->id);
        $truck->name = $request->name;
        $truck->description = $request->description;
        $truck->model = $request->model;
        $truck->max_pallets_number = $request->max_pallets_number;
        $truck->license_plate = $request->license_plate;
        $truck->photos = $request->photos;
        $truck->save();

        flash('Truck updated successfully')->success();
        return redirect()->route('trucks.index');
    }
}
