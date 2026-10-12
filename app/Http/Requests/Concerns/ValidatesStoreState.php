<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ValidatesStoreState
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function stateRules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'country_id' => ['required', 'integer', Rule::exists('countries', 'id')->where('status', 1)]];
    }
}
