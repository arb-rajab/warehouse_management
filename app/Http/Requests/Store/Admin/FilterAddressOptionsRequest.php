<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesSearchOptions;
use Illuminate\Foundation\Http\FormRequest;

class FilterAddressOptionsRequest extends FormRequest
{
    use ValidatesSearchOptions;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [...$this->searchOptionsRules(), 'country_id' => ['nullable', 'integer', 'exists:countries,id'], 'state_id' => ['nullable', 'integer', 'exists:states,id']];
    }
}
