<?php

namespace App\Http\Requests\Store\Admin\Concerns;

trait ValidatesTax
{
    /**
     * @return array<string, list<string>>
     */
    protected function taxRules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
