<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The query parameters the in-house and all-products lists share. They keep
 * the legacy Blade list's names, so bookmarks and the retained seller list's
 * links still apply: `type` is the legacy `column,direction` sort, now
 * limited to the six orders the Blade list offered, and the state switches
 * are GET-query booleans.
 */
trait FiltersStoreProductList
{
    use FiltersPerPage;
    use NormalizesBooleanFilters;

    /**
     * The `type` values the legacy list's "Sort by" select offered, and the
     * order each one applies.
     *
     * @var array<string, array{string, 'asc'|'desc'}>
     */
    private const array PRODUCT_LIST_SORTS = [
        'rating,desc' => ['rating', 'desc'],
        'rating,asc' => ['rating', 'asc'],
        'num_of_sale,desc' => ['num_of_sale', 'desc'],
        'num_of_sale,asc' => ['num_of_sale', 'asc'],
        'unit_price,desc' => ['unit_price', 'desc'],
        'unit_price,asc' => ['unit_price', 'asc'],
    ];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function productListFilterRules(): array
    {
        return [
            ...$this->perPageRules(),
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'string', Rule::in(array_keys(self::PRODUCT_LIST_SORTS))],
            'active' => ['nullable', 'boolean'],
            'for_sale' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The column and direction of the validated legacy sort, if any.
     *
     * @return array{string, 'asc'|'desc'}|null
     */
    public function productListSort(): ?array
    {
        $sort = $this->validated('type');

        return is_string($sort) ? self::PRODUCT_LIST_SORTS[$sort] : null;
    }

    /**
     * @param  list<string>  $fields
     */
    protected function normalizeProductListFlags(array $fields): void
    {
        foreach (['active', 'for_sale', 'featured', ...$fields] as $field) {
            $this->normalizeBooleanFilter($field);
        }
    }
}
