<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesRowLetter;
use App\Models\Row;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'letter' => ['required', 'string', 'max:2', 'unique:rows,letter,'.$row->id],
            'cells_count' => ['required', 'integer', 'min:1'],
            'flats_count' => ['required', 'integer', 'min:1'],
        ];
    }
}
