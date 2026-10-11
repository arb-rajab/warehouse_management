<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ValidatesStoreCity
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function cityRules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'state_id' => ['required', 'integer', Rule::exists('states', 'id')->where('status', 1)], 'cost' => ['required', 'numeric', 'min:0', 'max:999999999999999999.99', 'decimal:0,2']];
    }
}
