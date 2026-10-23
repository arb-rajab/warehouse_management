<?php

namespace App\Http\Requests\Concerns;

use App\Enums\StoreProductList;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The `list` parameter the shared product-list row actions send: the list it was fired from, which bounds the products the
 * action may touch and is where it returns. Seller aliases fix the list
 * through a server-side route default, independently of query input.
 */
trait ValidatesStoreProductList
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function productListRules(): array
    {
        return ['list' => [$this->route('productList') === 'seller' ? 'nullable' : 'required', 'string', Rule::enum(StoreProductList::class)]];
    }

    public function productList(): StoreProductList
    {
        return StoreProductList::from($this->route('productList') ?? $this->string('list')->toString());
    }
}
