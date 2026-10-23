<?php

namespace App\Http\Requests\Seller;

use Illuminate\Validation\Rule;

/** The read-only SKU preview accepts only its inputs, never arbitrary product ids. */
class PhysicalProductVariantsRequest extends PhysicalProductRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = array_filter(parent::rules(), fn ($key) => in_array($key, ['name', 'unit_price', 'colors_active', 'colors', 'colors.*', 'choice_no', 'choice_no.*'], true)
            || str_starts_with($key, 'choice_options_') || preg_match('/^(price|sku|qty|img)_/', $key), ARRAY_FILTER_USE_KEY);
        foreach ($rules as $key => &$fieldRules) {
            if (preg_match('/^(price|sku|qty|img)_/', $key)) {
                $fieldRules = ['sometimes', ...$fieldRules];
            }
        }
        unset($fieldRules);
        // A choice change posts the previous table too. Validate those old
        // row values, but do not treat them as rows to persist or generate.
        foreach (array_slice(array_keys($this->all()), 0, 2000) as $key) {
            if (preg_match('/^(price|sku|qty|img)_[^.*\[\]\\\\]{1,255}$/uD', $key, $match) !== 1) {
                continue;
            }
            $rules[$key] = match ($match[1]) {
                'price' => ['nullable', ...array_slice($rules['unit_price'], 1)],
                'qty' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
                'sku' => ['nullable', 'string', 'max:255'],
                'img' => ['nullable', 'integer', Rule::exists('uploads', 'id')->where('user_id', $this->user('store')?->id)->where('type', 'image')],
            };
        }
        $rules['name'] = ['nullable', 'string', 'max:200'];
        $rules['id'] = ['nullable', 'integer', Rule::exists('products', 'id')->where('user_id', $this->user('store')?->id)];

        return $rules;
    }
}
