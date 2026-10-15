<?php

namespace App\Http\Requests\Concerns;

use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesStoreCampaign
{
    use ValidatesStoreUploadReference;

    protected function prepareForValidation(): void
    {
        foreach (['start_date', 'end_date'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
                $this->merge([$field => $value.':00']);
            }
        }
    }

    /** @return array<string, mixed> */
    protected function campaignRules(int $titleWidth): array
    {
        return [
            'title' => ['required', 'string', 'max:'.$titleWidth],
            'banner' => ['nullable', 'integer', $this->imageUploadRule()],
            'start_date' => ['required', 'date_format:Y-m-d\TH:i:s', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date_format:Y-m-d\TH:i:s', 'after_or_equal:start_date'],
            'products' => ['required', 'array', 'min:1', 'max:1000'],
            'products.*' => ['required', 'array:id,discount,discount_type,quantity'],
            'products.*.id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('published', 1)->where('approved', 1)],
            'products.*.discount' => ['required', 'numeric', 'min:0'],
            'products.*.discount_type' => ['required', Rule::in(['amount', 'percent'])],
        ];
    }

    /** @return array<Closure(Validator): void> */
    protected function campaignPercentages(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('products', []) as $index => $row) {
                if (is_array($row) && ($row['discount_type'] ?? null) === 'percent' && is_numeric($row['discount'] ?? null) && $row['discount'] > 100) {
                    $validator->errors()->add("products.$index.discount", __('validation.max.numeric', ['attribute' => 'discount', 'max' => 100]));
                }
            }
        }];
    }
}
