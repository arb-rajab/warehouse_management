<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersCellStatusLogs;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterCellStatusLogsRequest extends FormRequest
{
    use FiltersCellStatusLogs;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->cellStatusLogFilterRules();
    }
}
