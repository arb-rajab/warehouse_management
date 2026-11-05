<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesSearchOptions;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The order configuration's default-product search: a part of the product id of
 * at least four characters (StoreSearchSelect sends nothing shorter).
 */
class FilterProductSearchRequest extends FormRequest
{
    use ValidatesSearchOptions;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [...$this->searchOptionsRules(), 'q' => ['required', 'string', 'min:4', 'max:100']];
    }
}
