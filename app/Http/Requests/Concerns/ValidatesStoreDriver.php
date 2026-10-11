<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait ValidatesStoreDriver
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function driverRules(bool $passwordRequired): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:30'],
            'password' => [$passwordRequired ? 'required' : 'nullable', 'string', 'min:6', 'max:255', 'confirmed'],
        ];
    }
}
