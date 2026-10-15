<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreTrip;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The trip edit form, for the bound trip: its stored checkpoints may be
 * kept (by id) but not moved to another order, customer or trip.
 */
class UpdateTripRequest extends FormRequest
{
    use ValidatesStoreTrip;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->tripRules(), ...$this->checkpointRules()];
    }

    /** @return array<callable> */
    public function after(): array
    {
        /** @var Trip $trip */
        $trip = $this->route('trip');

        return [fn (Validator $validator) => $this->validateCheckpointReferences($validator, $trip->id)];
    }
}
