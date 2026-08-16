<?php

namespace App\Http\Requests\Concerns;

use App\Enums\CellLogAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared filter validation for requests listing cell status logs (the admin
 * page and the mobile API listing).
 */
trait FiltersCellStatusLogs
{
    use FiltersByRowAndExpiration;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function cellStatusLogFilterRules(): array
    {
        return [
            'product_id' => ['nullable', 'array'],
            'product_id.*' => ['integer', 'exists:products,id'],
            // No `exists:pallets,id` — a pallet is hard-deleted once emptied, but its
            // logs (and this filter) must keep working against its old id.
            'pallet_id' => ['nullable', 'integer'],
            ...$this->rowAndExpirationFilterRules(),
            'user_id' => ['nullable', 'array'],
            'user_id.*' => ['integer', 'exists:users,id'],
            'action' => ['nullable', 'array'],
            'action.*' => [Rule::enum(CellLogAction::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            // Alternative to date_from/date_to, not a companion to them — mutually
            // exclusive so the two ways of expressing the same range can't conflict.
            'created_within_days' => ['nullable', 'integer', 'min:1', 'prohibits:date_from,date_to'],
            // Same mutual-exclusion pattern as created_within_days, but for the
            // expiration range instead of the created-at range.
            'expires_within_days' => ['nullable', 'integer', 'min:1', 'prohibits:expiration_date_from,expiration_date_to'],
            'sort_by' => ['nullable', 'in:created_at,expiration_date'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
