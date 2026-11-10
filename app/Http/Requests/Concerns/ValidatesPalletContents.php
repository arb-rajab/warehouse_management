<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation for requests that place a new pallet into a slot — what
 * the pallet holds and when it expires, independent of how the slot itself is
 * addressed (route-bound cell on the admin side, coordinates on the API side).
 * The product rule alone is also what a receipt's pallets are checked by.
 */
trait ValidatesPalletContents
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function palletContentsRules(): array
    {
        return [
            'product_id' => $this->palletProductRules(),
            'expiration_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * The rules a pallet's product id is validated by, wherever it sits in
     * the payload (`product_id`, `pallets.*.product_id`).
     *
     * @return list<string>
     */
    protected function palletProductRules(): array
    {
        return ['required', 'integer', 'exists:products,id'];
    }
}
