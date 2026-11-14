<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersByProductIds;
use App\Http\Requests\Concerns\FiltersByWarehouseIds;
use App\Http\Requests\Concerns\NormalizesBooleanFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /api/v1/pallets`: the admin's pallet list to select from. Every filter
 * is optional; omitting them all lists every current pallet, placed and
 * unplaced.
 */
class FilterPalletsRequest extends FormRequest
{
    use FiltersByProductIds;
    use FiltersByWarehouseIds;
    use NormalizesBooleanFilters;

    /** The largest page the admin may ask for (they select many at once). */
    public const int MAX_PER_PAGE = 200;

    public const int DEFAULT_PER_PAGE = 20;

    protected function prepareForValidation(): void
    {
        $this->normalizeBooleanFilter('placed');
        $this->normalizeBooleanFilter('has_note');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->productIdsFilterRules(),
            ...$this->warehouseIdsFilterRules(),
            /** `true`: only pallets in a cell; `false`: only received pallets with no cell yet. */
            'placed' => ['nullable', 'boolean'],
            /** `true`: only pallets with a note; `false`: only pallets without one. */
            'has_note' => ['nullable', 'boolean'],
            /** Contains-match, case-insensitive, on the pallet's note or its product's `name` / `ar_name`. */
            'q' => ['nullable', 'string', 'max:255'],
            /** Only the pallets created by finalizing this receipt. */
            'receipt_id' => ['nullable', 'integer'],
            /** Pallets per page, 1 to 200 (default 20). */
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    /**
     * The validated `placed`/`has_note` filter, or null when it was not sent.
     */
    public function booleanFilter(string $field): ?bool
    {
        return $this->filled($field) ? $this->boolean($field) : null;
    }

    /**
     * The trimmed search term, or null when there is none.
     */
    public function searchTerm(): ?string
    {
        $term = trim($this->string('q')->value());

        return $term === '' ? null : $term;
    }

    public function perPage(): int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : self::DEFAULT_PER_PAGE;
    }
}
