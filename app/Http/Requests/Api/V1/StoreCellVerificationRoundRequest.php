<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCellVerificationRoundRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // A round always names the rows it walks — there is no "all rows"
            // shorthand, since the rows it claims are what other rounds are
            // then refused and what pallet actions are frozen against.
            'row_ids' => ['required', 'array', 'min:1'],
            'row_ids.*' => ['integer', 'distinct', 'exists:rows,id'],
        ];
    }

    /**
     * The requested row ids, as ints — `exists:rows,id` has already proved
     * every one of them resolves.
     *
     * @return list<int>
     */
    public function rowIds(): array
    {
        return array_values(array_map('intval', $this->array('row_ids')));
    }
}
