<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation for requests that narrow a listing to a created-at window,
 * expressed either as an explicit from/to range or as a rolling "last N days".
 */
trait FiltersByDateRange
{
    /**
     * `created_within_days` is an alternative to `date_from`/`date_to`, not a
     * companion to them — `prohibits` keeps the two ways of expressing the same
     * range from conflicting.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function dateRangeFilterRules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'created_within_days' => ['nullable', 'integer', 'min:1', 'prohibits:date_from,date_to'],
        ];
    }
}
