<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersPerPage;
use App\Http\Requests\Concerns\SortsByDirection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query params for a user's detail page, which renders two independently
 * paginated tables (status logs and verification reports) sharing one sort
 * direction.
 */
class ShowUserRequest extends FormRequest
{
    use FiltersPerPage;
    use SortsByDirection;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->perPageRules(),
            ...$this->perPageRules('reports_per_page'),
            'sort_by' => ['nullable', 'in:created_at,expiration_date'],
            ...$this->sortDirectionRules(),
        ];
    }
}
