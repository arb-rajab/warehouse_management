<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait ValidatesStoreTax
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function taxRules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
