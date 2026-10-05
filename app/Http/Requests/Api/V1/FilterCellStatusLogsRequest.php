<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersByProductStatus;
use App\Http\Requests\Concerns\FiltersCellStatusLogs;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterCellStatusLogsRequest extends FormRequest
{
    use FiltersByProductStatus;
    use FiltersCellStatusLogs;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->cellStatusLogFilterRules(),
            ...$this->productStatusFilterRules(),
        ];
    }
}
