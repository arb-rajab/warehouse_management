<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreTrip;
use App\Http\Requests\Concerns\ValidatesStoreTripList;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A trip's notes, saved from the list on change: the same rule as the trip
 * edit form's notes field.
 */
class UpdateTripNotesRequest extends FormRequest
{
    use ValidatesStoreTrip;
    use ValidatesStoreTripList;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->tripListRules(), 'notes' => $this->tripRules()['notes']];
    }
}
