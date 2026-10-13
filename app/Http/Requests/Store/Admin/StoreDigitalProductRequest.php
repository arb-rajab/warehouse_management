<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreDigitalProduct;
use Illuminate\Foundation\Http\FormRequest;

class StoreDigitalProductRequest extends FormRequest
{
    use ValidatesStoreDigitalProduct;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->digitalProductRules(null);
    }
}
