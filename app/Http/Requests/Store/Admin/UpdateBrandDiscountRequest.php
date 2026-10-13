<?php

namespace App\Http\Requests\Store\Admin;

use Closure;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandDiscountRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'discount' => ['required', 'numeric', 'min:0'],
            'seller_product_discount' => ['sometimes', 'boolean'],
            'date_range' => ['bail', 'nullable', 'string', 'max:42', function (string $attribute, mixed $value, Closure $fail): void {
                $dates = explode(' to ', $value);
                if (count($dates) !== 2 || preg_match('/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2} to \d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}$/D', $value) !== 1) {
                    $fail(__('validation.date_format', ['attribute' => $attribute, 'format' => 'd-m-Y H:i:s to d-m-Y H:i:s']));

                    return;
                }
                $start = DateTimeImmutable::createFromFormat('!d-m-Y H:i:s', $dates[0]);
                $end = DateTimeImmutable::createFromFormat('!d-m-Y H:i:s', $dates[1]);
                if ($start === false || $end === false || $start->format('d-m-Y H:i:s') !== $dates[0] || $end->format('d-m-Y H:i:s') !== $dates[1] || $end < $start) {
                    $fail(__('validation.date_format', ['attribute' => $attribute, 'format' => 'd-m-Y H:i:s to d-m-Y H:i:s']));
                }
            }],
        ];
    }
}
