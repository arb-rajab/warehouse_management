<?php

namespace App\Http\Requests\Store;

use App\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRepresentativeVisitNoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('visit') instanceof Visit) {
            $this->merge(['visit_id' => $this->route('visit')->id]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['visit_id' => ['required', 'integer'], 'note' => ['nullable', 'string']];
    }
}
