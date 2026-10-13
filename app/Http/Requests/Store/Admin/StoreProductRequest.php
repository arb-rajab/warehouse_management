<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesDiscountDateRange;
use App\Http\Requests\Concerns\ValidatesStoreProductTaxes;
use App\Http\Requests\Concerns\ValidatesStoreProductVariants;
use App\Http\Requests\Concerns\ValidatesStoreUploadReference;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The physical product create form. Every field the legacy Blade form posted,
 * with the `products` column widths: the name VARCHAR(200) (as is
 * `product_translations.name`), the unit VARCHAR(20), tags VARCHAR(500),
 * photos VARCHAR(2000), the video link VARCHAR(100), the external link
 * VARCHAR(500), amounts DECIMAL(20,2), the weight DECIMAL(8,2) and the
 * quantities INT. The description and meta texts are MEDIUMTEXT/LONGTEXT and
 * have no length rule; the description is cleaned by App\Services\HtmlSanitizer
 * before validation.
 *
 * The shipping, flash deal, tax and cash-on-delivery fields count only while
 * the business settings that showed them on the Blade form are on; otherwise
 * they are excluded, as the form never posted them.
 */
class StoreProductRequest extends FormRequest
{
    use ValidatesDiscountDateRange;
    use ValidatesStoreProductTaxes;
    use ValidatesStoreProductVariants;
    use ValidatesStoreUploadReference;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $int = ['integer', 'max:2147483647'];
        $variantCount = count($this->variantNamesOrNone());
        $vat = self::vatSectionEnabled();
        $taxes = array_map(fn (array $rules): array => $vat ? $rules : ['exclude'], $this->productTaxRules());

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
            'product_barcodes.*' => ['nullable', 'string', 'max:255', 'distinct', Rule::unique('product_barcodes', 'barcode')],
            'photos' => ['nullable', 'string', 'max:2000', $this->retainableImageUploadIdsRule(null)],
            'thumbnail_img' => ['nullable', 'integer', $this->imageUploadRule()],
            'video_provider' => ['nullable', 'string', Rule::in(['youtube', 'dailymotion', 'vimeo'])],
            'video_link' => ['nullable', 'string', 'max:100', 'url:http,https'],
            'pdf' => ['nullable', 'integer', $this->uploadReferenceRule('document')],
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
            'variants.*.image' => ['nullable', 'integer', $this->imageUploadRule()],
            'external_link' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'external_link_btn' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'meta_img' => ['nullable', 'integer', $this->imageUploadRule()],
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
            'button' => ['nullable', 'string', Rule::in(['publish', 'unpublish'])],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
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
