<?php

namespace App\Http\Requests\Concerns;

use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ValidatesStoreZone
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function zoneRules(): array
    {
        $zone = $this->route('zone');

        return [
            'name' => ['required', 'string', 'max:255'],
            'country_id' => ['required', 'array', 'min:1'],
            'country_id.*' => ['required', 'integer', 'distinct', Rule::exists('countries', 'id')->where(fn ($query) => $query->where('status', 1)->where(fn ($query) => $query->where('zone_id', 0)->orWhere('zone_id', $zone instanceof Zone ? $zone->id : 0)))],
        ];
    }
}
