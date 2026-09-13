<?php

namespace App\Http\Requests\Concerns;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Mobile API-only filter: resolves a caller-chosen `active`/`inactive`
 * `product_status` into a concrete `product_id` list once validation has
 * passed, so the existing `product_id`-driven filtering (FiltersByProductIds,
 * CellStatusLog::filtered(), BuildsDashboardStats) picks it up unchanged.
 * Not used by the Admin equivalents, which keep `product_id[]` only.
 *
 * The resolution happens in `passedValidation()` rather than
 * `prepareForValidation()` so the merged-in `product_id` never collides with
 * the `prohibits` rule below, which must see the caller's own raw input.
 */
trait FiltersByProductStatus
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function productStatusFilterRules(): array
    {
        return [
            'product_status' => ['nullable', Rule::in(['active', 'inactive']), 'prohibits:product_id,product_id.*'],
        ];
    }

    protected function resolveProductStatusFilter(): void
    {
        if (! $this->filled('product_status') || $this->filled('product_id')) {
            return;
        }

        $ids = Product::query()
            ->where('published', $this->input('product_status') === 'active')
            ->pluck('id')
            ->all();

        // `filled()` treats an empty array as blank — both `CellStatusLog::filtered()`
        // and `FiltersByProductIds::productIds()` gate on `$request->filled('product_id')`,
        // so merging `[]` here would make the filter disappear instead of matching
        // zero rows. A sentinel id that can never exist (ids are positive
        // auto-increments) keeps the array non-blank while still matching nothing.
        $this->merge(['product_id' => $ids !== [] ? $ids : [-1]]);
    }
}
