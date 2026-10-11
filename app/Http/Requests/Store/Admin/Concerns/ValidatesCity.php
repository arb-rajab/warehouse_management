<?php

namespace App\Http\Requests\Store\Admin\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesCity
{
    /**
     * @return array<string, mixed>
     */
    protected function cityRules(bool $editing): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'cost' => ['required', 'numeric', 'min:0', 'max:999999999999999999.99'],
            'lang' => $editing
                ? ['required', 'string', Rule::exists('languages', 'code')]
                : ['prohibited'],
        ];
    }
}
