<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreTripList;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting one trip from the trip lists or the map picker.
 */
class TripListActionRequest extends FormRequest
{
    use ValidatesStoreTripList;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->tripListRules();
    }
}
