<?php

namespace App\Http\Requests\Store\Admin;

use App\Enums\TripStatus;
use App\Http\Requests\Concerns\ValidatesStoreTripList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTripStatusRequest extends FormRequest
{
    use ValidatesStoreTripList;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->tripListRules(), 'status' => ['required', 'string', Rule::enum(TripStatus::class)]];
    }
}
