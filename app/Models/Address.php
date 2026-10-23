<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = ['set_default', 'address', 'country_id', 'city_id', 'state_id', 'postal_code', 'phone', 'longitude', 'latitude'];

    /**
     * Writes what the storefront's and the seller panel's address forms send
     * (ValidatesAddress), as those forms always have: a column the form left
     * out (state and city on the storefront, the postal code in the seller
     * panel, the coordinates without the map) is cleared.
     *
     * @param  array<string, mixed>  $validated
     */
    public function fillFromForm(array $validated): void
    {
        foreach (['address', 'country_id', 'state_id', 'city_id', 'longitude', 'latitude', 'postal_code', 'phone'] as $column) {
            $this->{$column} = $validated[$column] ?? null;
        }
    }

    public function user()
    {
        return $this->belongsTo(StoreUser::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<State, $this> */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
