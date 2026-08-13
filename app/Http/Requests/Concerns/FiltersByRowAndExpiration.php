<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared row/column/expiration-date-range filter validation, used by any
 * request that lists cells or cell status logs by their slot location and
 * pallet expiration.
 */
trait FiltersByRowAndExpiration
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function rowAndExpirationFilterRules(): array
    {
        return [
            'row_id' => ['nullable', 'integer', 'exists:rows,id'],
            'column_number' => ['nullable', 'integer', 'min:1'],
            'expiration_date_from' => ['nullable', 'date'],
            'expiration_date_to' => ['nullable', 'date', 'after_or_equal:expiration_date_from'],
        ];
    }
}
