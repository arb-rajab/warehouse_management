<?php

namespace App\Http\Requests\Store\Admin\Concerns;

use App\Models\Currency;
use Illuminate\Validation\Rule;

trait ValidatesCurrency
{
    /**
     * @return array<string, mixed>
     */
    protected function currencyRules(?Currency $currency = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('currencies', 'code')->ignore($currency),
            ],
            'exchange_rate' => ['required', 'numeric', 'min:0', 'max:99999.99999'],
        ];
    }
}
