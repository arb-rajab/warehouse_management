<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesRowLetter;
use App\Models\Row;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'letter' => ['required', 'string', 'max:2', 'unique:rows,letter'],
            'cells_count' => ['required', 'integer', 'min:1', 'max:'.Row::MAX_DIMENSION],
            'flats_count' => ['required', 'integer', 'min:1', 'max:'.Row::MAX_DIMENSION],
        ];
    }
}
