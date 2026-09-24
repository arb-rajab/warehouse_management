<?php

namespace App\Http\Controllers\Trip;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trips\DriverRequest;
use Illuminate\Http\Request;
use App\Models\User;
use Hash;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        $drivers = User::drivers();
        $sort_search = null;

        if ($request->has('search')) {

            $sort_search = $request->search;

            $drivers->where(function ($q) use ($sort_search) {
                $q->where('name', 'like', '%' . $sort_search . '%')
                    ->orWhere('email', 'like', '%' . $sort_search . '%');
            });
        }

        $drivers = $drivers->paginate(15);
        return view('backend.trips.drivers.index', compact('drivers', 'sort_search'));
    }

    public function create()
    {
        $countries = \App\Models\Country::where('status', 1)->orderBy('priority')->get();

        return view('backend.trips.drivers.create', compact('countries'));
    }

    public function store(DriverRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            // 'company_name' => $request->company_name,
            'user_type' => 'driver',
            'email' => $request->email,
            'password' => Hash::make($request->password),
            // 'company_address' => $request->company_address,
            // 'shipping_address' => $request->shipping_address,
            // 'tax_number' => $request->tax_number,
            'email_verified_at' => now(),
            // 'bank_account_number' => $request->bank_account_number,
            'phone' => $request->phone,
            'country' => $request->country,
            'registration_completed' => 1,
        ]);

        $user->save();

        flash('Driver Created Successfully')->success();
        return redirect()->route('drivers.index');
    }

    public function edit($id)
    {
        $driver = User::find($id);

        $countries = \App\Models\Country::where('status', 1)->orderBy('priority')->get();

        return view('backend.trips.drivers.edit', compact('driver', 'countries'));
    }

    public function update(Request $request)
    {
        $driver = User::findOrFail($request->id);

        $driver->name = $request->name;

        $driver->phone = $request->phone;
        $driver->country = $request->country;
        if (isset($request->password)) {
            $driver->password = Hash::make($request->password);
        }

        $driver->save();

        flash('Driver updated successfully')->success();
        return redirect()->route('drivers.index');
    }


    public function delete($id)
    {
        $driver = User::find($id);
        if ($driver) {

            $driver->trips()->each(function ($trip) {
                $trip->driver_id = null;
                $trip->save();
            });

            $driver->delete();
            flash('Driver Deleted Successfully')->success();
        } else {
            flash('Error: Driver Not Found')->error();
        }
        return redirect()->route('drivers.index');
    }
}
