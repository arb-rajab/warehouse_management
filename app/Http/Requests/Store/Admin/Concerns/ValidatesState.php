<?php

namespace App\Http\Requests\Store\Admin\Concerns;

trait ValidatesState
{
    /**
     * @return array<string, list<string>>
     */
    protected function stateRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
        ];
    }
}
