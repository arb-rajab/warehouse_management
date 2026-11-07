<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The query of every typeahead endpoint behind `StoreSearchSelect.vue`: a
 * search term and a page. An endpoint spreads it into its own `rules()` next to
 * the parameters it adds (`kind`, `state_id`).
 */
trait ValidatesSearchOptions
{
    /** @return array<string, array<int, ValidationRule|string>> */
    protected function searchOptionsRules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
