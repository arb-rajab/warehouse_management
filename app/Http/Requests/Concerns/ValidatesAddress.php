<?php

namespace App\Http\Requests\Concerns;

use App\Models\Country;
use Illuminate\Validation\Rule;

/**
 * The address a storefront customer or a seller saves for themselves
 * (`addresses`, 2026_09_28_000001_create_addresses_table): every text column
 * is a varchar(255), the coordinates are decimal(17,15), which holds less
 * than 100 either way, and the ids name an active country, an active state
 * of that country and an active city of that state.
 *
 * Only the phone is always required, as it was before these rules existed.
 * The country is required while the store has an active country to choose:
 * carrier-wise shipping reads the zone of the address's country and fails
 * without one, and with no countries set up the forms cannot offer one. Each
 * request adds what its own form requires on top.
 */
trait ValidatesAddress
{
    /** @return array<string, array<mixed>> */
    protected function addressRules(): array
    {
        return [
            'address' => ['nullable', 'string', 'max:255'],
            'country_id' => [Rule::requiredIf(fn (): bool => Country::active()->exists()), 'nullable', 'integer', Rule::exists('countries', 'id')->where('status', 1)],
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')->where('country_id', $this->integer('country_id'))->where('status', 1)],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->where('state_id', $this->integer('state_id'))->where('status', 1)],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'gt:-100', 'lt:100'],
        ];
    }
}
