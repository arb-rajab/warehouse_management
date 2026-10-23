<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Http\Requests\Concerns\ValidatesSellerProductFields;
use App\Http\Requests\Concerns\ValidatesStoreProductTaxes;
use App\Models\Category;
use App\Models\Product;
use App\Services\MultipartUploadService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DigitalProductRequest extends FormRequest
{
    use RejectsUnexpectedInput;
    use ValidatesSellerProductFields;
    use ValidatesStoreProductTaxes;

    public function authorize(): bool
    {
        $product = $this->route('id');

        return ! $product instanceof Product || Gate::forUser($this->user('store'))->allows('updateAsSeller', $product);
    }

    protected function failedAuthorization(): void
    {
        flash(translate('This product is not yours.'))->warning();

        throw new HttpResponseException(back());
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('description'))) {
            $this->merge(['description' => clean_html($this->input('description'))]);
        }
        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => Str::lower($this->input('slug'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ownImage = fn () => Rule::exists('uploads', 'id')->where('user_id', $this->user('store')?->id)->where('type', 'image');
        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', Rule::in(array_column(Category::treeOptions(fn ($roots) => $roots->where('digital', 1)), 'id'))],
            'unit_price' => ['required', ...self::AMOUNT_RULES],
            'purchase_price' => ['required', ...self::AMOUNT_RULES],
            'discount' => ['required', ...self::AMOUNT_RULES, Rule::when($this->input('discount_type') === 'percent', 'lte:100', 'lte:unit_price')],
            'discount_type' => ['required', 'string', Rule::in(['amount', 'percent'])],
            'tags' => ['nullable', 'array', 'list', 'max:1'],
            'tags.0' => ['nullable', 'string', 'max:10000', $this->tagsRule()],
            'photos' => ['nullable', 'string', 'max:2000', $this->photosRule()],
            'thumbnail_img' => ['nullable', 'integer', $ownImage()],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'meta_img' => ['nullable', 'integer', $ownImage()],
            'file' => ['required', 'integer', Rule::exists('uploads', 'id')->where('user_id', $this->user('store')?->id)
                ->whereIn('type', array_values(array_unique(MultipartUploadService::KINDS)))],
            ...$this->productTaxRules(),
        ];
        if ($this->route('id') instanceof Product) {
            $rules['lang'] = ['required', 'string', Rule::exists('languages', 'code')];
            $rules['slug'] = ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\-]+$/D', Rule::unique('products', 'slug')->ignore($this->route('id'))];
        }

        return $rules;
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('tax_type', []) as $index => $type) {
                if ($type === 'percent' && is_numeric($this->input('tax.'.$index)) && $this->input('tax.'.$index) > 100) {
                    $validator->errors()->add('tax.'.$index, __('validation.max.numeric', ['attribute' => 'tax', 'max' => 100]));
                }
            }
        }];
    }

    /** @return array<string, mixed> */
    public function productData(): array
    {
        $data = $this->validated();
        $data['tags'] = implode(',', array_column(json_decode($data['tags'][0] ?? '[]', true) ?? [], 'value'));

        return $data;
    }
}
