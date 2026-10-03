<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\AddressCollection;
use App\Http\Resources\V2\CitiesCollection;
use App\Http\Resources\V2\CountriesCollection;
use App\Http\Resources\V2\StatesCollection;
use App\Models\Address;
use App\Models\Cart;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AddressController extends Controller
{
    private function manageableAddress(mixed $id): Address
    {
        $address = Address::findOrFail($id);
        Gate::authorize('manage', $address);

        return $address;
    }

    public function addresses()
    {
        if(auth()->user()->is_rep){
            $current_customer = auth()->user()->current_customer;

            if($current_customer){
                $customer_addresses = $current_customer->addresses;
            }else {
                $customer_addresses = auth()->user()->addresses;
            }

            return new AddressCollection($customer_addresses);
        }
        return new AddressCollection(Address::where('user_id',auth()->user()->id)->get());
    }

    public function createShippingAddress(Request $request)
    {
        $address = new Address;
        if(auth()->user()->is_rep){
            $current_customer   = auth()->user()->current_customer;
            $address->user_id   = $current_customer->id ?? auth()->user()->id;
        }else{
            $address->user_id = auth()->user()->id;
        }
        $address->address = $request->address;
        $address->country_id = $request->country_id;
        $address->state_id = $request->state_id;
        $address->city_id = $request->city_id;
        $address->postal_code = $request->postal_code;
        $address->phone = $request->phone;
        $address->save();

        return response()->json([
            'result' => true,
            'message' => translate('Shipping information has been added successfully')
        ]);
    }

    public function updateShippingAddress(Request $request)
    {
        $address = $this->manageableAddress($request->id);
        $address->address = $request->address;
        $address->country_id = $request->country_id;
        $address->state_id = $request->state_id;
        $address->city_id = $request->city_id;
        $address->postal_code = $request->postal_code;
        $address->phone = $request->phone;
        $address->save();

        return response()->json([
            'result' => true,
            'message' => translate('Shipping information has been updated successfully')
        ]);
    }

    public function updateShippingAddressLocation(Request $request)
    {
        $address = $this->manageableAddress($request->id);
        $address->latitude = $request->latitude;
        $address->longitude = $request->longitude;
        $address->save();

        return response()->json([
            'result' => true,
            'message' => translate('Shipping location in map updated successfully')
        ]);
    }


    public function deleteShippingAddress($id)
    {
        $address = Address::find($id);
        if($address == null || Gate::denies('manage', $address)) {
            return response()->json([
                'result' => false,
                'message' => translate('Address not found')
            ]);
        }
        $address->delete();
        return response()->json([
            'result' => true,
            'message' => translate('Shipping information has been deleted')
        ]);
    }

    public function makeShippingAddressDefault(Request $request)
    {
        $address = $this->manageableAddress($request->id);
        Address::where('user_id', $address->user_id)->update(['set_default' => 0]); //make all the owner's addresses non default first
        $address->refresh();

        $address->set_default = 1;
        $address->save();
        return response()->json([
            'result' => true,
            'message' => translate('Default shipping information has been updated')
        ]);
    }

    public function updateAddressInCart(Request $request)
    {
        $this->manageableAddress($request->address_id);

        try {
            Cart::where('user_id', auth()->user()->id)->update(['address_id' => $request->address_id]);

        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => translate('Could not save the address')
            ]);
        }
        return response()->json([
            'result' => true,
            'message' => translate('Address is saved')
        ]);


    }


    public function getShippingInCart(Request $request)
    {
        set_time_limit(0);

           $cart= Cart::where('user_id', auth()->user()->id)->first();

           $address = $cart->address;
           return new AddressCollection(Address::where('id', $address->id)->get());
        //    return  new AddressCollection($address);

    }

    public function updateShippingTypeInCart(Request $request)
    {
        try {
            set_time_limit(0);

           $carts= Cart::where('user_id', auth()->user()->id)->get();


           foreach ($carts as $key => $cart) {

            $cart->shipping_cost = 0;

           if($request->shipping_type=="pickup_point"){
            $cart->shipping_type="pickup_point";
            $cart->pickup_point=$request->shipping_id;
            $cart->carrier_id=0;
           }
           else if($request->shipping_type=="home_delivery"){
            $cart->shipping_cost = getShippingCost($carts, $key);
            $cart->shipping_type="home_delivery";
            $cart->pickup_point=0;
            $cart->carrier_id=0;
           }
           else if($request->shipping_type=="carrier_base"){
            $cart->shipping_cost = getShippingCost($carts, $key,$cart->carrier_id);
            $cart->shipping_type="carrier";
            $cart->carrier_id=$request->shipping_id;
            $cart->pickup_point=0;
           }
           $cart->save();

        }

        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => translate('Could not save the address')
            ]);
        }
        return response()->json([
            'result' => true,
            'message' => translate('Delivery address is saved')
        ]);


    }


    public function getCities(Request $request)
    {
        $city_query = City::where('status', 1);
        if ($request->name != "" || $request->name != null) {
            $city_query->where('name', 'like', '%' . $request->name . '%');
        }
        $cities = $city_query->orderBy('name', 'asc')->get();
        return new CitiesCollection($cities);
    }

    public function getStates(Request $request)
    {
        $state_query = State::where('status', 1);
        if ($request->name != "" || $request->name != null) {
            $state_query->where('name', 'like', '%' . $request->name . '%');
        }
        $states = $state_query->orderBy('name', 'asc')->get();
        return new StatesCollection($states);
    }

    public function getCountries(Request $request)
    {
        $country_query = Country::where('status', 1);
        if ($request->name != "" || $request->name != null) {
            $country_query->where('name', 'like', '%' . $request->name . '%');
        }
        $countries = $country_query->orderBy('priority', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        return new CountriesCollection($countries);
    }

    public function getCitiesByState($state_id,Request $request)
    {
        $city_query = City::where('status', 1)->where('state_id',$state_id);
        if ($request->name != "" || $request->name != null) {
             $city_query->where('name', 'like', '%' . $request->name . '%');
        }
        $cities = $city_query->get();
        return new CitiesCollection($cities);
    }

    public function getStatesByCountry($country_id,Request $request)
    {
        $state_query = State::where('status', 1)->where('country_id',$country_id);
        if ($request->name != "" || $request->name != null) {
            $state_query->where('name', 'like', '%' . $request->name . '%');
       }
        $states = $state_query->get();
        return new StatesCollection($states);
    }
}
