<?php

namespace App\Http\Requests\Concerns;

/**
 * Shared `expired` boolean normalization for requests filtering by it.
 */
trait NormalizesExpiredFilter
{
    use NormalizesBooleanFilters;

    /**
     * @see NormalizesBooleanFilters::normalizeBooleanFilter()
     */
    protected function normalizeExpiredFilter(): void
    {
        $this->normalizeBooleanFilter('expired');
    }
}
