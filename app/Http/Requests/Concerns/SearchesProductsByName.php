<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation for requests that filter products by a caller-typed
 * search term (`q`), matched against `Product::searchByName()`.
 */
trait SearchesProductsByName
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function productSearchRules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
        ];
    }
}
