<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The physical product create form: the fields it shares with the edit form
 * (ValidatesStoreProduct, which documents the widths and the settings that
 * switch sections off) and the button that publishes or not.
 */
class StoreProductRequest extends FormRequest
{
    use ValidatesStoreProduct;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->productRules(null),
            'button' => ['nullable', 'string', Rule::in(['publish', 'unpublish'])],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return $this->productAfter();
    }
}
