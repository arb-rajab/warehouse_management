<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Store\Admin\Concerns\ValidatesCurrency;
use App\Models\Currency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrencyRequest extends FormRequest
{
    use ValidatesCurrency;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Currency $currency */
        $currency = $this->route('currency');

        return $this->currencyRules($currency);
    }
}
