<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesRowLetter;
use App\Models\Row;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRowRequest extends FormRequest
{
    use NormalizesRowLetter;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            // Unique within the chosen warehouse only: another warehouse may have its own row A.
            'letter' => ['required', 'string', 'max:2', Rule::unique('rows', 'letter')->where('warehouse_id', $this->integer('warehouse_id'))],
            'cells_count' => ['required', 'integer', 'min:1', 'max:'.Row::MAX_DIMENSION],
            'flats_count' => ['required', 'integer', 'min:1', 'max:'.Row::MAX_DIMENSION],
        ];
    }
}
