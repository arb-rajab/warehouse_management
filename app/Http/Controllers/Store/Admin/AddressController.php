<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\ChecksCustomerAccount;
use App\Http\Controllers\Store\Admin\Concerns\FlashesSuccess;
use App\Http\Controllers\Store\Admin\Concerns\SearchesOptions;
use App\Http\Requests\Store\Admin\FilterAddressOptionsRequest;
use App\Http\Requests\Store\Admin\StoreCustomerAddressRequest;
use App\Models\Address;
use App\Models\City;
use App\Models\State;
use App\Models\StoreUser;
use App\Support\SearchOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller implements HasMiddleware
{
    use ChecksCustomerAccount;
    use FlashesSuccess;
    use SearchesOptions;

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
        $this->flashSuccess('address_has_been_created_successfully');

        return redirect()->route('customers.edit', $customer);
    }

    public function update(StoreCustomerAddressRequest $request, StoreUser $customer, Address $address): RedirectResponse
    {
        $this->assertOwnedAddress($customer, $address);
        $address->fill($request->validated());
        $address->save();
        $this->flashSuccess('address_has_been_updated_successfully');

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
        $this->flashSuccess('address_has_been_deleted_successfully');

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
        $this->flashSuccess('default_address_has_been_updated_successfully');

        return redirect()->route('customers.edit', $customer);
    }

    /** The typeahead behind a customer address's state (StoreSearchSelect): the enabled states of a country. */
    public function states(FilterAddressOptionsRequest $request): JsonResponse
    {
        $term = $this->searchTerm($request);
        $states = State::query()->select(['id', 'name', 'country_id'])->with('country:id,name')
            ->where('status', 1)->where('country_id', $request->integer('country_id'));
        if ($term !== '') {
            $this->whereWordsMatch($states, ['name'], $term);
        }
        $this->orderByRelevance($states, 'name', $term);

        return $this->optionsPage($states, fn (State $state): array => SearchOption::state($state));
    }

    /** The typeahead behind a customer address's city (StoreSearchSelect): the enabled cities of a state. */
    public function cities(FilterAddressOptionsRequest $request): JsonResponse
    {
        $term = $this->searchTerm($request);
        $cities = City::query()->select(['id', 'name', 'state_id'])->with(['city_translations:id,city_id,lang,name', 'state:id,name'])
            ->where('status', 1)->where('state_id', $request->integer('state_id'));
        if ($term !== '') {
            $this->whereTranslatedNameMatches($cities, 'city_translations', $term);
        }
        $this->orderByRelevance($cities, 'name', $term);

        return $this->optionsPage($cities, fn (City $city): array => SearchOption::city($city));
    }

    /** A bound address belongs to its bound parent even for Super Admin. */
    private function assertOwnedAddress(StoreUser $customer, Address $address): void
    {
        $this->assertCustomer($customer);
        abort_unless((int) $address->user_id === $customer->id, 403);
    }
}
