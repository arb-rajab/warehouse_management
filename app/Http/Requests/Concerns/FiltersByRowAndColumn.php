<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation for requests that filter by a row/column-number pair.
 */
trait FiltersByRowAndColumn
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function rowAndColumnFilterRules(): array
    {
        return [
            'row_id' => ['nullable', 'integer', 'exists:rows,id'],
            'column_number' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
