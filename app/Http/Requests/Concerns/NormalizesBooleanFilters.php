<?php

namespace App\Http\Requests\Concerns;

/**
 * Shared GET-query boolean normalization for requests filtering by one, keyed by field name.
 */
trait NormalizesBooleanFilters
{
    /**
     * Normalize a field before validation — it arrives as the query-string literal
     * "true"/"false" (how a JS boolean serializes into a GET link), which Laravel's
     * `boolean` rule rejects outright since it only accepts true/false/1/0/'1'/'0'.
     * Values that aren't recognizably boolean (e.g. "bogus") are left untouched so
     * the `boolean` rule still rejects them.
     */
    protected function normalizeBooleanFilter(string $field): void
    {
        if (! $this->has($field)) {
            return;
        }

        $normalized = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($normalized !== null) {
            $this->merge([$field => $normalized]);
        }
    }
}
