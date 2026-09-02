<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersCellStatusLogs;
use App\Http\Requests\Concerns\FiltersPerPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterCellStatusLogsRequest extends FormRequest
{
    use FiltersCellStatusLogs, FiltersPerPage;

    /**
     * The `per_page` selector is admin-only — the mobile API listing
     * (Api\V1\FilterCellStatusLogsRequest) also uses FiltersCellStatusLogs
     * but must not gain it, so this overrides the trait's rules() instead of
     * adding perPageRules() to cellStatusLogFilterRules() itself.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->cellStatusLogFilterRules(),
            ...$this->perPageRules(),
        ];
    }
}
