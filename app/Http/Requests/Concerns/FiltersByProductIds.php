<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation + accessor for requests that filter by a caller-chosen
 * set of product ids.
 */
trait FiltersByProductIds
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function productIdsFilterRules(): array
    {
        return [
            'product_id' => ['nullable', 'array'],
            'product_id.*' => ['integer', 'exists:products,id'],
        ];
    }

    /**
     * The validated product ids, or null when the caller didn't filter by product.
     *
     * @return list<int>|null
     */
    public function productIds(): ?array
    {
        return $this->filled('product_id') ? array_values(array_map('intval', $this->array('product_id'))) : null;
    }
}
