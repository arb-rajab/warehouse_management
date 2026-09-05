<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersCellVerificationReports;
use App\Http\Requests\Concerns\FiltersPerPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterCellVerificationReportsRequest extends FormRequest
{
    use FiltersCellVerificationReports, FiltersPerPage;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->cellVerificationReportFilterRules(),
            ...$this->perPageRules(),
        ];
    }
}
