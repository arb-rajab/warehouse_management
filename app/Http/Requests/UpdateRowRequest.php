<?php

namespace App\Http\Requests;

use App\Models\Row;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateRowRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('letter')) {
            $this->merge(['letter' => Str::upper($this->string('letter'))]);
        }
    }

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
