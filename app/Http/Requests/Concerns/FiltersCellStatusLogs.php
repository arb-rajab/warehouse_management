<?php

namespace App\Http\Requests\Concerns;

use App\Enums\CellLogAction;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared filter validation for requests listing cell status logs (the admin
 * page and the mobile API listing).
 */
trait FiltersCellStatusLogs
{
    use FiltersByProductIds;
    use FiltersByRowAndColumn;
    use NormalizesBooleanFilters;

    protected function prepareForValidation(): void
    {
        $this->normalizeBooleanFilter('flagged');

        if (! $this->viewerMaySeeFlags()) {
            $this->merge(['flagged' => false]);
        }
    }

    /**
     * The `flagged` filter is admin-only, and dropping it is a boundary concern
     * rather than a query one: `CellStatusLog::filtered()` stays a pure function
     * of the request's filter params. Narrowing a listing to flagged rows would
     * reveal the rule-based anti-fraud state by inclusion even though
     * `CellStatusLogResource` withholds the `flagged`/`flags` keys themselves,
     * and those flags are raised against the very worker doing the reading.
     * Neutralized to `false` rather than removed so the `boolean` rule below
     * still sees a valid value.
     */
    private function viewerMaySeeFlags(): bool
    {
        $viewer = $this->user();

        return $viewer instanceof User && $viewer->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->cellStatusLogFilterRules();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function cellStatusLogFilterRules(): array
    {
        return [
            ...$this->productIdsFilterRules(),
            // No `exists:pallets,id` — a pallet is hard-deleted once emptied, but its
            // logs (and this filter) must keep working against its old id.
            'pallet_id' => ['nullable', 'integer'],
            ...$this->rowAndColumnFilterRules(),
            'expiration_date_from' => ['nullable', 'date'],
            'expiration_date_to' => ['nullable', 'date', 'after_or_equal:expiration_date_from'],
            'user_id' => ['nullable', 'array'],
            'user_id.*' => ['integer', Rule::exists(User::class, 'id')],
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
            'flagged' => ['nullable', 'boolean'],
        ];
    }
}
