<?php

namespace App\Http\Requests\Concerns;

use App\Models\Offer;
use App\Models\Product;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesStoreOffer
{
    use ValidatesStoreCampaign;

    /** @return array<string, mixed> */
    protected function offerRules(): array
    {
        $offer = $this->route('offer');

        return $this->campaignRules(255) + [
            'offer_type' => ['required', Rule::in($offer instanceof Offer ? [$offer->offer_type] : ['products_offer', 'brand_offer'])],
            'base_product_id' => ['exclude_unless:offer_type,products_offer', 'required', 'integer', Rule::exists('products', 'id')->where('published', 1)->where('approved', 1)],
            'base_quantity' => ['required', 'numeric', 'gt:0'],
            'brand_id' => ['exclude_unless:offer_type,brand_offer', 'required', 'integer', Rule::exists('brands', 'id')],
            'products.*.quantity' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<Closure(Validator): void> */
    public function after(): array
    {
        return [...$this->campaignPercentages(), function (Validator $validator): void {
            if ($this->input('offer_type') === 'brand_offer' && is_numeric($this->input('brand_id')) && ! Product::query()->where('brand_id', $this->input('brand_id'))->where('published', 1)->where('approved', 1)->exists()) {
                $validator->errors()->add('brand_id', __('validation.exists', ['attribute' => 'brand']));
            }
            if ($this->input('offer_type') === 'products_offer' && is_numeric($this->input('base_quantity')) && floor((float) $this->input('base_quantity')) != (float) $this->input('base_quantity')) {
                $validator->errors()->add('base_quantity', __('validation.integer', ['attribute' => 'quantity']));
            }
        }];
    }
}
