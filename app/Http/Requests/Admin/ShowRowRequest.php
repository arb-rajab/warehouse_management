<?php

namespace App\Http\Requests\Admin;

use App\Models\Row;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowRowRequest extends FormRequest
{
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
            'cell_number' => ['nullable', 'integer', 'min:1', 'max:'.$row->cells_count],
            'flat_number' => ['nullable', 'integer', 'min:1', 'max:'.$row->flats_count],
        ];
    }
}
