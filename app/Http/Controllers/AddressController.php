<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Models\Address;
use App\Models\Cart;
use App\Models\City;
use App\Models\State;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(StoreAddressRequest $request)
    {
        $address = new Address;
        $address->user_id = $request->ownerId();
        $this->fillFromForm($address, $request->validated());
        $address->save();

        flash(translate('Address info Stored successfully'))->success();
        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $data['address_data'] = Address::findOrFail($id);
        Gate::authorize('manage', $data['address_data']);
        $data['states'] = State::where('status', 1)->where('country_id', $data['address_data']->country_id)->get();
        $data['cities'] = City::where('status', 1)->where('state_id', $data['address_data']->state_id)->get();

        $returnHTML = view('shop.frontend.partials.address_edit_modal', $data)->render();
        return response()->json(['data' => $data, 'html'=>$returnHTML]);
//        return ;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(UpdateAddressRequest $request, $id)
    {
        $address = $request->targetAddress();
        $this->fillFromForm($address, $request->validated());
        $address->save();

        flash(translate('Address info updated successfully'))->success();
        return back();
    }

    /**
     * Writes what the address forms send, as before: a field left out of the
     * form (state and city, and the coordinates without the map) is cleared.
     *
     * @param  array<string, mixed>  $validated
     */
    private function fillFromForm(Address $address, array $validated): void
    {
        foreach (['address', 'country_id', 'state_id', 'city_id', 'longitude', 'latitude', 'postal_code', 'phone'] as $column) {
            $address->{$column} = $validated[$column] ?? null;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $address = Address::findOrFail($id);
        Gate::authorize('manage', $address);
        if(!$address->set_default){
            $address->delete();
            return back();
        }
        flash(translate('Default address can not be deleted'))->warning();
        return back();
    }

    public function getStates(Request $request) {
        $states = State::where('status', 1)->where('country_id', $request->country_id)->get();
        $html = '<option value="">'.translate("Select State").'</option>';

        foreach ($states as $state) {
            $html .= '<option value="' . $state->id . '">' . $state->name . '</option>';
        }

        echo json_encode($html);
    }

    public function getCities(Request $request) {
        $cities = City::where('status', 1)->where('state_id', $request->state_id)->get();
        $html = '<option value="">'.translate("Select City").'</option>';

        foreach ($cities as $row) {
            $html .= '<option value="' . $row->id . '">' . $row->getTranslation('name') . '</option>';
        }

        echo json_encode($html);
    }

    public function set_default($id){
        Gate::authorize('manage', Address::findOrFail($id));

        foreach (Auth::user()->addresses as $key => $address) {
            $address->set_default = 0;
            $address->save();
        }
        $address = Address::findOrFail($id);
        $address->set_default = 1;
        $address->save();

        return back();
    }
}
