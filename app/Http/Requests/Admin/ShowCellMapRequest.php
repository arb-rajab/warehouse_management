<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersByProductIds;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowCellMapRequest extends FormRequest
{
    use FiltersByProductIds;

    /**
     * Normalize `expired` before validation — it arrives as the query-string
     * literal "true"/"false" (how a JS boolean serializes into a GET link),
     * which Laravel's `boolean` rule rejects outright since it only accepts
     * true/false/1/0/'1'/'0'. Values that aren't recognizably boolean (e.g.
     * "bogus") are left untouched so the `boolean` rule still rejects them.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('expired')) {
            return;
        }

        $normalized = filter_var($this->input('expired'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($normalized !== null) {
            $this->merge(['expired' => $normalized]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'flat_number' => ['nullable', 'integer', 'min:1'],
            'state' => ['nullable', 'string', 'in:empty,full,opened'],
            'expires_within_days' => ['nullable', 'integer', 'min:0'],
            'expired' => ['nullable', 'boolean'],
            ...$this->productIdsFilterRules(),
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
