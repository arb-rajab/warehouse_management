<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRowRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'letter' => ['required', 'string', 'max:2', 'unique:rows,letter'],
            'cells_count' => ['required', 'integer', 'min:1'],
            'flats_count' => ['required', 'integer', 'min:1'],
        ];
    }
}
