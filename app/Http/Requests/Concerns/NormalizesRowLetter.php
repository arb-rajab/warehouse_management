<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;

/**
 * Normalizes a Row's `letter` field to uppercase before validation, so the
 * database always stores what the UI displays.
 */
trait NormalizesRowLetter
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('letter')) {
            $this->merge(['letter' => Str::upper($this->string('letter'))]);
        }
    }
}
