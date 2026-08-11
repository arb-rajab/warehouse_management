<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation for requests that accept an optional free-text note.
 */
trait ValidatesOptionalNote
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->noteRules();
    }

    /**
     * The note field's rules, for requests that merge it with other rules.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function noteRules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
