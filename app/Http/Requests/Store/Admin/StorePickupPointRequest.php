<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Store\Admin\Concerns\ValidatesPickupPoint;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePickupPointRequest extends FormRequest
{
    use ValidatesPickupPoint;

    protected function prepareForValidation(): void
    {
        $this->preparePickupPointForValidation();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->pickupPointRules(editing: false);
    }
}
