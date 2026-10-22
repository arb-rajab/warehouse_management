<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Http\Requests\Concerns\ValidatesDiscountDateRange;
use App\Http\Requests\Concerns\ValidatesStoreProductTaxes;
use App\Models\Product;
use App\Models\Upload;
use App\Services\Store\ProductVariantCombinations;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Validates the existing Blade wire format, including its dynamic SKU keys. */
class PhysicalProductRequest extends FormRequest
{
    use RejectsUnexpectedInput;
    use ValidatesDiscountDateRange;
    use ValidatesStoreProductTaxes;

    public function authorize(): bool
    {
        $product = $this->route('product');

        return ! $product instanceof Product || Gate::forUser($this->user('store'))->allows('updateAsSeller', $product);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('description'))) {
            $this->merge(['description' => clean_html($this->input('description'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $int = ['integer', 'min:0', 'max:2147483647'];
        $ownImage = fn () => Rule::exists('uploads', 'id')->where('user_id', $this->user('store')?->id)->where('type', 'image');
        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', 'min:1', 'max:2147483647', Rule::exists('categories', 'id')->where('digital', 0)],
            'brand_id' => ['nullable', 'integer', 'min:1', 'max:2147483647', Rule::exists('brands', 'id')],
            'unit' => ['required', 'string', 'max:20'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
            'min_qty' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'tags' => ['nullable', 'array', 'list', 'max:1'],
            'tags.0' => ['nullable', 'string', 'max:10000', $this->tagsRule()],
            'barcode' => ['nullable', 'string', 'max:255'],
            'refundable' => ['prohibited'], // The retired refund addon has no products column.
            'photos' => ['nullable', 'string', 'max:2000', $this->photosRule()],
            'thumbnail_img' => ['nullable', 'integer', $ownImage()],
            'video_provider' => ['nullable', 'string', Rule::in(['youtube', 'dailymotion', 'vimeo'])],
            'video_link' => ['nullable', 'string', 'max:100', 'url:http,https'],
            'pdf' => ['nullable', 'integer', Rule::exists('uploads', 'id')->where('user_id', $this->user('store')?->id)->where('type', 'document')],
            'unit_price' => ['required', ...self::AMOUNT_RULES],
            'discount' => ['required', ...self::AMOUNT_RULES, Rule::when($this->input('discount_type') === 'percent', 'lt:100', 'lt:unit_price')],
            'discount_type' => ['required', 'string', Rule::in(['amount', 'percent'])],
            'date_range' => $this->discountDateRangeRules(),
            'current_stock' => ['required', ...$int],
            'sku' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string'], 'meta_description' => ['nullable', 'string'],
            'meta_img' => ['nullable', 'integer', $ownImage()],
            'external_link' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'external_link_btn' => ['nullable', 'string', 'max:255'],
            'shipping_type' => ['nullable', 'string', Rule::in(['free', 'flat_rate'])],
            'flat_shipping_cost' => ['nullable', 'required_if:shipping_type,flat_rate', ...self::AMOUNT_RULES],
            'is_quantity_multiplied' => ['nullable', 'boolean'],
            'est_shipping_days' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'low_stock_quantity' => ['nullable', ...$int],
            'stock_visibility_state' => ['required', 'string', Rule::in(['quantity', 'text', 'hide'])],
            'cash_on_delivery' => ['nullable', 'boolean'],
            'colors_active' => ['nullable', 'boolean'],
            'colors' => ['nullable', 'array', 'list', 'max:100'],
            'colors.*' => ['required', 'string', 'distinct', Rule::exists('colors', 'code')],
            'choice_attributes' => ['nullable', 'array', 'list', 'max:20'],
            'choice_attributes.*' => ['required', 'integer', 'distinct', Rule::exists('attributes', 'id')],
            'choice_no' => ['nullable', 'array', 'list', 'max:20'],
            'choice_no.*' => ['required', 'integer', 'distinct', Rule::exists('attributes', 'id')],
            'choice' => ['nullable', 'array', 'list', 'max:20'], 'choice.*' => ['nullable', 'string', 'max:255'],
            ...$this->productTaxRules(),
        ];
        if ($this->route('product') instanceof Product) {
            $rules['lang'] = ['required', 'string', Rule::exists('languages', 'code')];
            $rules['slug'] = ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\-]+$/D', Rule::unique('products', 'slug')->ignore($this->route('product'))];
        } else {
            $rules['button'] = ['nullable', 'string', Rule::in(['publish', 'unpublish', 'draft'])];
        }
        foreach ($this->choiceIds() as $id) {
            $key = 'choice_options_'.$id;
            $rules[$key] = ['required', 'array', 'list', 'min:1', 'max:100'];
            $rules[$key.'.*'] = ['required', 'string', 'distinct', 'max:255', Rule::exists('attribute_values', 'value')->where('attribute_id', $id)];
        }
        foreach ($this->variantNames() as $name) {
            $key = str_replace(['.', ' '], '_', $name);
            $rules['price_'.$key] = ['required', ...self::AMOUNT_RULES];
            $rules['sku_'.$key] = ['nullable', 'string', 'max:255'];
            $rules['qty_'.$key] = ['required', ...$int];
            $rules['img_'.$key] = ['nullable', 'integer', $ownImage()];
        }

        return $rules;
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $names = $this->variantNames();
            if ($names === [] && ($this->choiceIds() !== [] || ($this->boolean('colors_active') && $this->input('colors')))) {
                $validator->errors()->add('choice_no', __('validation.max.array', ['attribute' => 'variants', 'max' => 500]));
            }
            if (count(array_unique(array_map(fn ($name) => str_replace(['.', ' '], '_', $name), $names))) !== count($names)
                || collect($names)->contains(fn ($name) => mb_strlen($name) > 255 || preg_match('/[\[\]]/', $name))) {
                $validator->errors()->add('choice_no', __('validation.distinct', ['attribute' => 'variants']));
            }
            $dates = $this->input('date_range');
            if (is_string($dates) && preg_match('/^(.+) to (.+)$/D', $dates, $parts)) {
                if (strtotime($parts[1]) < -2147483648 || strtotime($parts[2]) > 2147483647) {
                    $validator->errors()->add('date_range', __('validation.date', ['attribute' => 'date_range']));
                }
            }
            foreach ((array) $this->input('tax_type', []) as $index => $type) {
                if ($type === 'percent' && is_numeric($this->input('tax.'.$index)) && $this->input('tax.'.$index) > 100) {
                    $validator->errors()->add('tax.'.$index, __('validation.max.numeric', ['attribute' => 'tax', 'max' => 100]));
                }
            }
        }];
    }

    /** @return list<int> Shape-safe before validation, never unbounded. */
    private function choiceIds(): array
    {
        $ids = $this->input('choice_no');

        return is_array($ids) && count($ids) <= 20 ? array_values(array_unique(array_map('intval', array_filter($ids, fn ($id) => is_scalar($id) && ctype_digit((string) $id))))) : [];
    }

    /** @return list<string> */
    public function variantNames(): array
    {
        $colors = $this->boolean('colors_active') && is_array($this->input('colors')) ? $this->input('colors') : [];
        $values = array_map(fn ($id) => $this->input('choice_options_'.$id), $this->choiceIds());
        if (count($colors) > 100 || collect([$colors, ...$values])->contains(fn ($row) => ! is_array($row) || count($row) > 100 || collect($row)->contains(fn ($item) => ! is_string($item)))
            || array_product(array_map('count', [$colors ?: [null], ...$values])) > 500) {
            return [];
        }

        return app(ProductVariantCombinations::class)->names(array_values(array_map('strval', $colors)), $values);
    }

    /**
     * Converts validated legacy keys into the shared writer's explicit data shape.
     *
     * @return array<string, mixed>
     */
    public function productData(): array
    {
        $data = $this->validated();
        $data['tags'] = implode(',', array_column(json_decode($data['tags'][0] ?? '[]', true) ?? [], 'value'));
        $data['choice_options'] = array_map(fn ($id) => ['attribute_id' => $id, 'values' => $data['choice_options_'.$id]], $this->choiceIds());
        $data['variants'] = array_map(function ($name) use ($data) {
            $key = str_replace(['.', ' '], '_', $name);

            return ['variant' => $name, 'price' => $data['price_'.$key], 'sku' => $data['sku_'.$key] ?? null, 'qty' => $data['qty_'.$key], 'image' => $data['img_'.$key] ?? null];
        }, $this->variantNames());

        return $data;
    }

    private function tagsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $tags = is_string($value) ? json_decode($value, true) : null;
            if (! is_array($tags) || ! array_is_list($tags) || count($tags) > 100
                || collect($tags)->contains(fn ($tag) => ! is_array($tag) || array_keys($tag) !== ['value'] || ! is_string($tag['value']) || trim($tag['value']) === '' || str_contains($tag['value'], ','))) {
                $fail(__('validation.array', ['attribute' => $attribute]));
            } elseif (mb_strlen(implode(',', array_column($tags, 'value'))) > 500) {
                $fail(__('validation.max.string', ['attribute' => $attribute, 'max' => 500]));
            }
        };
    }

    private function photosRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $ids = is_string($value) && preg_match('/^\d+(?:,\d+)*$/D', $value) ? explode(',', $value) : [];
            if ($ids === [] || count($ids) > 50 || count(array_unique($ids)) !== count($ids)
                || Upload::query()->where('user_id', $this->user('store')?->id)->where('type', 'image')->whereIn('id', $ids)->count() !== count($ids)) {
                $fail(__('validation.exists', ['attribute' => $attribute]));
            }
        };
    }
}
