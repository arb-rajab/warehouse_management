<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\ChecksCustomerAccount;
use App\Http\Requests\Store\Admin\FilterAddressOptionsRequest;
use App\Http\Requests\Store\Admin\StoreCustomerAddressRequest;
use App\Models\Address;
use App\Models\City;
use App\Models\State;
use App\Models\StoreUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller implements HasMiddleware
{
    use ChecksCustomerAccount;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [new Middleware('permission:edit_customer')];
    }

    public function store(StoreCustomerAddressRequest $request, StoreUser $customer): RedirectResponse
    {
        $this->assertCustomer($customer);
        DB::transaction(function () use ($request, $customer) {
            StoreUser::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $address = new Address;
            $address->fill($request->validated());
            $address->user_id = $customer->id;
            $address->set_default = $customer->addresses()->exists() ? 0 : 1;
            $address->save();
        });

        return redirect()->route('customers.edit', $customer);
    }

    public function update(StoreCustomerAddressRequest $request, StoreUser $customer, Address $address): RedirectResponse
    {
        $this->assertOwnedAddress($customer, $address);
        $address->fill($request->validated());
        $address->save();

        return redirect()->route('customers.edit', $customer);
    }

    public function destroy(StoreUser $customer, Address $address): RedirectResponse
    {
        $this->assertOwnedAddress($customer, $address);
        DB::transaction(function () use ($customer, $address) {
            StoreUser::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $currentAddress = $customer->addresses()->lockForUpdate()->findOrFail($address->id);
            abort_if((bool) $currentAddress->set_default, 403);
            $currentAddress->delete();
        });

        return redirect()->route('customers.edit', $customer);
    }

    public function setDefault(StoreUser $customer, Address $address): RedirectResponse
    {
        $this->assertOwnedAddress($customer, $address);
        DB::transaction(function () use ($customer, $address) {
            StoreUser::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $currentAddress = $customer->addresses()->lockForUpdate()->findOrFail($address->id);
            $customer->addresses()->update(['set_default' => 0]);
            $currentAddress->set_default = 1;
            $currentAddress->save();
        });

        return redirect()->route('customers.edit', $customer);
    }

    public function states(FilterAddressOptionsRequest $request): JsonResponse
    {
        return response()->json(State::query()->select(['id', 'name'])->where('status', 1)->where('country_id', $request->integer('country_id'))->orderBy('name')->get()->map(fn ($state) => ['id' => $state->id, 'name' => $state->name]));
    }

    public function cities(FilterAddressOptionsRequest $request): JsonResponse
    {
        return response()->json(City::query()->select(['id', 'name'])->where('status', 1)->where('state_id', $request->integer('state_id'))->with('city_translations:id,city_id,lang,name')->orderBy('name')->get()->map(fn ($city) => ['id' => $city->id, 'name' => $city->city_translations->firstWhere('lang', app()->getLocale())->name ?? $city->name]));
    }

    /** A bound address belongs to its bound parent even for Super Admin. */
    private function assertOwnedAddress(StoreUser $customer, Address $address): void
    {
        $this->assertCustomer($customer);
        abort_unless((int) $address->user_id === $customer->id, 403);
    }
}
