<?php

namespace App\Http\Requests\Concerns;

use App\Enums\StoreProductList;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The `list` parameter every row action of the in-house and all-products
 * lists sends: the list it was fired from, which bounds the products the
 * action may touch and is where it returns.
 */
trait ValidatesStoreProductList
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function productListRules(): array
    {
        return ['list' => ['required', 'string', Rule::enum(StoreProductList::class)]];
    }

    public function productList(): StoreProductList
    {
        return StoreProductList::from($this->string('list')->toString());
    }
}
