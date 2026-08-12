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
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function cellStatusLogFilterRules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            // No `exists:pallets,id` — a pallet is hard-deleted once emptied, but its
            // logs (and this filter) must keep working against its old id.
            'pallet_id' => ['nullable', 'integer'],
            'row_id' => ['nullable', 'integer', 'exists:rows,id'],
            'column_number' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'array'],
            'action.*' => [Rule::enum(CellLogAction::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            // Alternative to date_from/date_to, not a companion to them — mutually
            // exclusive so the two ways of expressing the same range can't conflict.
            'created_within_days' => ['nullable', 'integer', 'min:1', 'prohibits:date_from,date_to'],
            'expiration_date_from' => ['nullable', 'date'],
            'expiration_date_to' => ['nullable', 'date', 'after_or_equal:expiration_date_from'],
            'sort_by' => ['nullable', 'in:created_at,expiration_date'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
