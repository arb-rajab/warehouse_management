<?php

namespace App\Http\Requests\Store\Admin\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesZone
{
    /**
     * @return array<string, mixed>
     */
    protected function zoneRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'country_id' => ['required', 'array', 'min:1'],
            'country_id.*' => [
                'integer',
                'distinct',
                Rule::exists('countries', 'id')->where('status', 1),
            ],
        ];
    }
}
