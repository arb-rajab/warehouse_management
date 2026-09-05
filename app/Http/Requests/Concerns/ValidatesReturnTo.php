<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation for requests fired from a dialog that's used on more than one
 * page (the cell map and a row's page), so the controller knows which page to
 * redirect back to afterwards instead of guessing from unreliable Referer/session
 * state.
 */
trait ValidatesReturnTo
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function returnToRules(): array
    {
        return [
            'return_to' => ['nullable', 'in:cells,row'],
        ];
    }
}
