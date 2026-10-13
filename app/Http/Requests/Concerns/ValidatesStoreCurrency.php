<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait ValidatesStoreCurrency
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function currencyRules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'symbol' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:20'], 'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:99999.99999', 'decimal:0,5']];
    }
}
