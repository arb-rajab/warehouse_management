<?php

namespace App\Http\Requests\Concerns;

use App\Models\AttributeValue;
use App\Services\Store\ProductVariantCombinations;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** @mixin FormRequest */
trait ValidatesStoreProductVariants
{
    /**
     * The most variant rows one product may generate. The legacy form had no
     * bound and simply timed out on a large enough cartesian product.
     */
    public const int MAX_VARIANTS = 500;

    /**
     * The colours (`colors[]`, codes of `colors`, used only while
     * `colors_active` is on) and the chosen attributes with their values
     * (`choice_options[n][attribute_id]`, `choice_options[n][values][]`),
     * which together generate the variant rows. A value must be one of its
     * attribute's values, as the legacy picker offered them.
     *
     * @return array<string, mixed>
     */
    protected function productVariantRules(): array
    {
        return [
            'colors_active' => ['nullable', 'boolean'],
            'colors' => ['nullable', 'array', 'list', 'max:100'],
            'colors.*' => ['required', 'string', 'distinct', Rule::exists('colors', 'code')],
            'choice_options' => ['nullable', 'array', 'list', 'max:20'],
            'choice_options.*' => ['required', 'array:attribute_id,values'],
            'choice_options.*.attribute_id' => ['required', 'integer', 'distinct', Rule::exists('attributes', 'id')],
            'choice_options.*.values' => ['required', 'array', 'list', 'min:1', 'max:100', $this->distinctValuesRule()],
            'choice_options.*.values.*' => ['required', 'string', 'max:255', $this->attributeValueRule()],
        ];
    }

    /**
     * An attribute's values are distinct; two attributes may share a value.
     */
    private function distinctValuesRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_array($value) && count(array_unique(array_map('strval', array_filter($value, 'is_scalar')))) !== count($value)) {
                $fail(__('validation.distinct', ['attribute' => $attribute]));
            }
        };
    }

    /**
     * A value must belong to the attribute of its own choice row.
     */
    private function attributeValueRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $row = (int) explode('.', $attribute)[1];
            $attributeId = $this->input("choice_options.$row.attribute_id");
            if (! is_numeric($attributeId) || ! AttributeValue::query()->where('attribute_id', (int) $attributeId)->where('value', $value)->exists()) {
                $fail(__('validation.exists', ['attribute' => $attribute]));
            }
        };
    }

    /**
     * Refuses a choice that would generate more rows than MAX_VARIANTS or a
     * variant name wider than `product_stocks.variant`, or two variants with
     * the same name (values differing only in spaces).
     */
    protected function validateVariantCombinations(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }
        $count = array_product(array_map('count', [$this->variantColorCodes() ?: [null], ...$this->variantChoiceValues()]));
        if ($count > self::MAX_VARIANTS) {
            $validator->errors()->add('choice_options', __('validation.max.array', ['attribute' => 'variants', 'max' => self::MAX_VARIANTS]));

            return;
        }
        $names = $this->variantNames();
        if (collect($names)->contains(fn (string $name): bool => mb_strlen($name) > 255)) {
            $validator->errors()->add('choice_options', __('validation.max.string', ['attribute' => 'variant', 'max' => 255]));
        } elseif (count(array_unique($names)) !== count($names)) {
            $validator->errors()->add('choice_options', __('validation.distinct', ['attribute' => 'variants']));
        }
    }

    /**
     * The colour codes that count: none unless colours are on.
     *
     * @return list<string>
     */
    public function variantColorCodes(): array
    {
        $colors = $this->input('colors');

        return $this->boolean('colors_active') && is_array($colors) ? array_values(array_map('strval', $colors)) : [];
    }

    /**
     * Each chosen attribute's values, in the order chosen.
     *
     * @return list<list<string>>
     */
    public function variantChoiceValues(): array
    {
        $choices = $this->input('choice_options');

        return is_array($choices) ? array_values(array_map(fn (mixed $choice): array => array_values(array_map('strval', (array) ($choice['values'] ?? []))), $choices)) : [];
    }

    /** @return list<string> */
    public function variantNames(): array
    {
        return app(ProductVariantCombinations::class)->names($this->variantColorCodes(), $this->variantChoiceValues());
    }
}
