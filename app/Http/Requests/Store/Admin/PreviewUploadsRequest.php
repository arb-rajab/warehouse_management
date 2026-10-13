<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PreviewUploadsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('ids'))) {
            $this->merge(['ids' => explode(',', $this->input('ids'))]);
        }
    }

    /** Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['required', 'integer', 'min:1', 'distinct']];
    }
}
