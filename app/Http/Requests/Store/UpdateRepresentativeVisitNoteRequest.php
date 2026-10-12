<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRepresentativeVisitNoteRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['visit_id' => ['required', 'integer'], 'note' => ['nullable', 'string']];
    }
}
