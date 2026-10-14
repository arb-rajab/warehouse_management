<?php

namespace App\Http\Requests\Concerns;

use App\Models\Category;
use App\Models\Product;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The fields the physical product create and edit forms share. Every field the
 * legacy Blade forms posted, with the `products` column widths: the name
 * VARCHAR(200) (as is `product_translations.name`), the unit VARCHAR(20), tags
 * VARCHAR(500), photos VARCHAR(2000), the video link VARCHAR(100), the
 * external link VARCHAR(500), amounts DECIMAL(20,2), the weight DECIMAL(8,2)
 * and the quantities INT. The description and meta texts are
 * MEDIUMTEXT/LONGTEXT and have no length rule; the description is cleaned by
 * App\Services\HtmlSanitizer before validation.
 *
 * The shipping, flash deal, tax and cash-on-delivery fields count only while
 * the business settings that showed them on the Blade form are on; otherwise
 * they are excluded, as the form never posted them.
 *
 * On edit (`$product` given) a stored upload reference round-trips whoever
 * owns it, and the product's own store barcodes may be posted again.
 *
 * @mixin FormRequest
 */
trait ValidatesStoreProduct
{
    use ValidatesDiscountDateRange;
    use ValidatesStoreProductTaxes;
    use ValidatesStoreProductVariants;
    use ValidatesStoreUploadReference;

    /** @return array<string, mixed> */
    protected function productRules(?Product $product): array
    {
        $int = ['integer', 'max:2147483647'];
        $variantCount = count($this->variantNamesOrNone());
        $vat = self::vatSectionEnabled();
        $taxes = array_map(fn (array $rules): array => $vat ? $rules : ['exclude'], $this->productTaxRules());
        $barcode = Rule::unique('product_barcodes', 'barcode');
        if ($product !== null) {
            $barcode->whereNot('product_id', $product->id);
        }

        return [
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', Rule::in(array_column(Category::treeOptions(fn ($roots) => $roots->where('digital', 0)), 'id'))],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'unit' => ['required', 'string', 'max:20'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
            'max_qty' => ['required', ...$int, 'min:1'],
            'min_qty' => ['required', ...$int, 'min:1'],
            'tags' => ['nullable', 'string', 'max:500'],
            'product_types' => ['nullable', 'array', 'list', 'max:50'],
            'product_types.*' => ['nullable', 'string', 'max:255'],
            'product_barcodes' => ['nullable', 'array', 'list', 'max:50'],
            'product_barcodes.*' => ['nullable', 'string', 'max:255', 'distinct', $barcode],
            'photos' => ['nullable', 'string', 'max:2000', $this->retainableImageUploadIdsRule($product?->getAttribute('photos'))],
            'thumbnail_img' => ['nullable', 'integer', $product ? $this->retainableImageUploadRule('thumbnail_img', $product->thumbnail_img) : $this->imageUploadRule()],
            'video_provider' => ['nullable', 'string', Rule::in(['youtube', 'dailymotion', 'vimeo'])],
            'video_link' => ['nullable', 'string', 'max:100', 'url:http,https'],
            'pdf' => ['nullable', 'integer', $product ? $this->retainableUploadReferenceRule('pdf', $product->getAttribute('pdf'), 'document') : $this->uploadReferenceRule('document')],
            ...$this->productVariantRules(),
            'unit_price' => ['required', ...self::AMOUNT_RULES],
            'date_range' => $this->discountDateRangeRules(),
            // The legacy rule: a flat discount below the unit price, a
            // percentage below 100.
            'discount' => ['required', ...self::AMOUNT_RULES, Rule::when($this->input('discount_type') === 'percent', 'lt:100', 'lt:unit_price')],
            'discount_type' => ['required', 'string', Rule::in(['amount', 'percent'])],
            'current_stock' => ['required', ...$int, 'min:0'],
            'sku' => ['nullable', 'string', 'max:255'],
            'variants' => [$variantCount > 0 ? 'required' : 'prohibited', 'array', 'list', 'size:'.$variantCount],
            'variants.*' => ['required', 'array:variant,price,sku,qty,image'],
            'variants.*.variant' => ['required', 'string', 'max:255'],
            'variants.*.price' => ['required', ...self::AMOUNT_RULES],
            'variants.*.sku' => ['nullable', 'string', 'max:255'],
            'variants.*.qty' => ['required', ...$int, 'min:0'],
            ...$this->variantImageRules($product),
            'external_link' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'external_link_btn' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'meta_img' => ['nullable', 'integer', $product ? $this->retainableImageUploadRule('meta_img', $product->getAttribute('meta_img')) : $this->imageUploadRule()],
            'shipping_type' => self::productWiseShippingEnabled() ? ['required', 'string', Rule::in(['free', 'flat_rate'])] : ['exclude'],
            'flat_shipping_cost' => self::productWiseShippingEnabled() ? ['nullable', 'required_if:shipping_type,flat_rate', ...self::AMOUNT_RULES] : ['exclude'],
            'is_quantity_multiplied' => self::productWiseShippingEnabled() ? ['nullable', 'boolean'] : ['exclude'],
            'est_shipping_days' => self::shippingSectionEnabled() ? ['nullable', ...$int, 'min:1'] : ['exclude'],
            'low_stock_quantity' => ['nullable', ...$int, 'min:0'],
            'stock_visibility_state' => ['required', 'string', Rule::in(['quantity', 'text', 'hide'])],
            'cash_on_delivery' => self::cashPaymentEnabled() ? ['nullable', 'boolean'] : ['exclude'],
            'featured' => ['nullable', 'boolean'],
            'todays_deal' => ['nullable', 'boolean'],
            'flash_deal_id' => self::flashDealsEnabled() ? ['nullable', 'integer', Rule::exists('flash_deals', 'id')->where('status', 1)] : ['exclude'],
            'flash_discount' => self::flashDealsEnabled() ? ['nullable', 'required_with:flash_deal_id', ...self::AMOUNT_RULES] : ['exclude'],
            'flash_discount_type' => self::flashDealsEnabled() ? ['nullable', 'required_with:flash_deal_id', 'string', Rule::in(['amount', 'percent'])] : ['exclude'],
            ...$taxes,
        ];
    }

    /**
     * Refuses too many or clashing variants, then variant rows that do not
     * match, in order, the names the colours and choices generate.
     *
     * @return array<int, callable>
     */
    protected function productAfter(): array
    {
        return [
            fn (Validator $validator) => $this->validateVariantCombinations($validator),
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                foreach ($this->variantNames() as $index => $name) {
                    if ($this->input("variants.$index.variant") !== $name) {
                        $validator->errors()->add("variants.$index.variant", __('validation.in', ['attribute' => 'variant']));
                    }
                }
            },
        ];
    }

    /** Cleans the description, so what is validated is what is stored. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('description'))) {
            $this->merge(['description' => clean_html($this->input('description'))]);
        }
    }

    /**
     * On create every variant photo must pass the library rule. On edit a
     * row's photo may keep the image its stored stock row of the same name
     * holds, whoever owns it (the first such row when names repeat); any
     * other photo must pass the library rule.
     *
     * @return array<string, mixed>
     */
    private function variantImageRules(?Product $product): array
    {
        $rows = $this->input('variants');
        if ($product === null || ! is_array($rows)) {
            return ['variants.*.image' => ['nullable', 'integer', $this->imageUploadRule()]];
        }
        $stored = $product->stocks()->whereNotNull('image')->orderByDesc('id')->pluck('image', 'variant')->all();
        $rules = [];
        foreach (array_keys($rows) as $index) {
            $variant = $this->input("variants.$index.variant");
            $current = is_string($variant) || is_int($variant) ? ($stored[(string) $variant] ?? null) : null;
            $rules["variants.$index.image"] = ['nullable', 'integer', $this->retainableImageUploadRule("variants.$index.image", $current)];
        }

        return $rules;
    }

    /**
     * The variant names, or none while the colours and choices are not yet
     * valid (their own rules report that).
     *
     * @return list<string>
     */
    private function variantNamesOrNone(): array
    {
        $choices = $this->input('choice_options');
        $colors = $this->input('colors');
        if ((! is_array($choices) || $choices === []) && ! ($this->boolean('colors_active') && is_array($colors) && $colors !== [])) {
            return [];
        }

        return rescue(fn (): array => $this->variantNames(), [], false);
    }

    /**
     * A slug, once normalised as it is stored, that no other product uses.
     */
    protected function freeSlugRule(Product $product): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($product): void {
            $slug = is_string($value) ? Str::slug($value) : '';
            if ($slug !== '' && Product::query()->where('slug', $slug)->whereKeyNot($product->id)->exists()) {
                $fail(__('validation.unique', ['attribute' => $attribute]));
            }
        };
    }

    /** The Blade form showed the shipping section only with this setting on. */
    public static function shippingSectionEnabled(): bool
    {
        return (bool) get_setting('Setup_Shipping_section');
    }

    /** Free/flat-rate shipping per product, inside the shipping section. */
    public static function productWiseShippingEnabled(): bool
    {
        return self::shippingSectionEnabled() && get_setting('shipping_type') === 'product_wise_shipping';
    }

    public static function flashDealsEnabled(): bool
    {
        return (bool) get_setting('Flash_deals');
    }

    public static function vatSectionEnabled(): bool
    {
        return get_setting('VAT_TAX_section') == 1;
    }

    /** The cash-on-delivery switch showed only while cash payment is on. */
    public static function cashPaymentEnabled(): bool
    {
        return get_setting('cash_payment') == '1';
    }
}
