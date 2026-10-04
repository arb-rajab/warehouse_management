<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersCellStatusLogs;
use App\Http\Requests\Concerns\FiltersPerPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterCellStatusLogsRequest extends FormRequest
{
    use FiltersCellStatusLogs;
    use FiltersPerPage;

    /**
     * Get the validation rules that apply to the request.
     *
     * The `per_page` selector is admin-only — the mobile API listing
     * (Api\V1\FilterCellStatusLogsRequest) also uses FiltersCellStatusLogs
     * but must not gain it, so this spreads the shared filter rules and adds perPageRules() instead of
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
