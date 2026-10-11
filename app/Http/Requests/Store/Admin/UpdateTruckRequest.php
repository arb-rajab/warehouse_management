<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreTruck;
use App\Models\Truck;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTruckRequest extends FormRequest
{
    use ValidatesStoreTruck;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Truck $truck */
        $truck = $this->route('truck');

        return $this->truckRules($truck);
    }
}
