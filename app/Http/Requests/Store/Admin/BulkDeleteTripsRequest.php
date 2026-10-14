<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreTripList;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDeleteTripsRequest extends FormRequest
{
    use ValidatesStoreTripList;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->tripListRules(),
            'id' => ['required', 'array', 'min:1', 'max:100'],
            'id.*' => ['required', 'integer', 'distinct', Rule::exists('trips', 'id')],
        ];
    }
}
