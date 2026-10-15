<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait RepresentativePackageRules
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function representativePackageRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'percentage' => ['required', 'numeric', 'between:0,100'],
        ];
    }
}
