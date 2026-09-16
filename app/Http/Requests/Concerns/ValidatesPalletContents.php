<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation for requests that place a new pallet into a slot — what
 * the pallet holds and when it expires, independent of how the slot itself is
 * addressed (route-bound cell on the admin side, coordinates on the API side).
 */
trait ValidatesPalletContents
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function palletContentsRules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
