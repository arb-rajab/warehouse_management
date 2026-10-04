<?php

namespace App\Http\Controllers\Trip;

use App\Http\Controllers\Controller;
use App\Http\Requests\Trips\DriverRequest;
use App\Models\Country;
use App\Models\StoreUser;
use Hash;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        $drivers = StoreUser::drivers();
        $sort_search = null;

        if ($request->has('search')) {

            $sort_search = $request->search;

            $drivers->where(function ($q) use ($sort_search) {
                $q->where('name', 'like', '%' . $sort_search . '%')
                    ->orWhere('email', 'like', '%' . $sort_search . '%');
            });
        }

        $drivers = $drivers->paginate(15);
        return view('shop.backend.trips.drivers.index', compact('drivers', 'sort_search'));
    }

    public function create()
    {
        $countries = Country::where('status', 1)->orderBy('priority')->get();

        return view('shop.backend.trips.drivers.create', compact('countries'));
    }

    public function store(DriverRequest $request)
    {
        $user = StoreUser::create([
            'name' => $request->name,
            // 'company_name' => $request->company_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            // 'company_address' => $request->company_address,
            // 'shipping_address' => $request->shipping_address,
            // 'tax_number' => $request->tax_number,
            // 'bank_account_number' => $request->bank_account_number,
            'phone' => $request->phone,
            'country' => $request->country,
            'registration_completed' => 1,
        ]);

        $user->forceFill(['user_type' => 'driver', 'email_verified_at' => now()])->save();

        flash('Driver Created Successfully')->success();
        return redirect()->route('drivers.index');
    }

    public function edit($id)
    {
        $driver = StoreUser::drivers()->findOrFail($id);

        $countries = Country::where('status', 1)->orderBy('priority')->get();

        return view('shop.backend.trips.drivers.edit', compact('driver', 'countries'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'name' => 'required|string|max:255',
            'phone' => 'required|string',
            'country' => 'required|string',
            'password' => 'nullable|string|min:6',
        ]);

        $driver = StoreUser::drivers()->findOrFail($request->id);

        $driver->name = $request->name;

        $driver->phone = $request->phone;
        $driver->country = $request->country;
        if ($request->filled('password')) {
            $driver->password = Hash::make($request->password);
        }

        $driver->save();

        flash('Driver updated successfully')->success();
        return redirect()->route('drivers.index');
    }


    public function delete($id)
    {
        $driver = StoreUser::drivers()->findOrFail($id);

        $driver->trips()->each(function ($trip) {
            $trip->driver_id = null;
            $trip->save();
        });

        $driver->delete();
        flash('Driver Deleted Successfully')->success();

        return redirect()->route('drivers.index');
    }
}
