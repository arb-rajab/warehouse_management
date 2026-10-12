<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Mobile API-only filter: resolves a caller-chosen `active`/`inactive`
 * `product_status` into a synthetic `product_published` boolean once
 * validation has passed, so downstream relational filtering
 * (CellStatusLog::filtered(), BuildsDashboardStats) can join against
 * `products.published` directly instead of materializing an id list. Kept
 * as a private merge key rather than a validated field, the same way
 * `product_id` was merged before it — so it never reaches the Admin
 * equivalents (FilterCellStatusLogsRequest/ShowDashboardRequest), which
 * don't use this trait and keep `product_id[]` only.
 *
 * The resolution happens in `passedValidation()` rather than
 * `prepareForValidation()` so the merged-in `product_published` never
 * collides with the `prohibits` rule below, which must see the caller's own
 * raw input.
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

    protected function passedValidation(): void
    {
        $this->resolveProductStatusFilter();
    }

    protected function resolveProductStatusFilter(): void
    {
        if (! $this->filled('product_status')) {
            return;
        }

        $this->merge(['product_published' => $this->input('product_status') === 'active']);
    }

    /**
     * The product-published filter derived from `product_status`, or null
     * when the caller didn't filter by product status. `Api\V1\ProductController::index()`
     * treats null as `true` (active only) — every other consumer of this
     * trait keeps null meaning "no filter".
     */
    public function productPublished(): ?bool
    {
        return $this->filled('product_published') ? $this->boolean('product_published') : null;
    }
}
