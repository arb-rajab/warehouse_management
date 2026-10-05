<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation for requests that let the caller flag pallets stale after
 * a caller-chosen number of days — there is no fixed "stale" threshold.
 */
trait FiltersByStaleAfterDays
{
    /**
     * The stale_after_days field's rules, for requests that merge it with other rules.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function staleAfterDaysFilterRules(): array
    {
        return [
            'stale_after_days' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
