<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreDigitalProduct;
use App\Http\Requests\Concerns\ValidatesStoreLanguage;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDigitalProductRequest extends FormRequest
{
    use ValidatesStoreDigitalProduct;
    use ValidatesStoreLanguage;

    /**
     * The slug is MEDIUMTEXT, so it has no length rule; it is lowercased on
     * save, as before.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');

        return [
            ...$this->digitalProductRules($product instanceof Product ? $product : null),
            ...$this->languageRules('required'),
            'slug' => ['nullable', 'string'],
        ];
    }
}
