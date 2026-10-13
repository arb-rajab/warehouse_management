<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreCarrier;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCarrierRequest extends FormRequest
{
    use ValidatesStoreCarrier;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->carrierRules($this->route('carrier'));
    }
}
