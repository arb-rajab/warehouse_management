<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesRowLetter;
use App\Models\Row;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRowRequest extends FormRequest
{
    use NormalizesRowLetter;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Row $row */
        $row = $this->route('row');

        return [
            // Unique within the row's own warehouse, which an update does not change.
            'letter' => ['required', 'string', 'max:2', Rule::unique('rows', 'letter')->where('warehouse_id', $row->warehouse_id)->ignore($row->id)],
            'cells_count' => ['required', 'integer', 'min:1', 'max:'.Row::MAX_DIMENSION],
            'flats_count' => ['required', 'integer', 'min:1', 'max:'.Row::MAX_DIMENSION],
        ];
    }
}
