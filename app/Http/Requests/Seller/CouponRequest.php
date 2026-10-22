<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Models\Coupon;
use DateTimeImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use LogicException;

class CouponRequest extends \App\Http\Requests\CouponRequest
{
    use RejectsUnexpectedInput;

    public function authorize(): bool
    {
        $coupon = $this->route('coupon');

        return ! $coupon instanceof Coupon || Gate::forUser($this->user('store'))->allows('update', $coupon);
    }

    /** Seller dates and details are derived only after validation. */
    protected function prepareForValidation(): void {}

    /** Get the validation rules that apply to the request.
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['product_base', 'cart_base'])],
            'code' => ['required', 'string', 'max:255', Rule::unique('coupons', 'code')->ignore($this->route('coupon'))],
            'discount_type' => ['required', 'string', Rule::in(['amount', 'percent'])],
            'discount' => ['required', 'numeric', 'min:1', 'max:999999999999999999.99', ...($this->input('discount_type') === 'percent' ? ['max:100'] : [])],
            'date_range' => ['bail', 'required', 'string', 'max:23', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($this->dates() === null) {
                    $fail(__('validation.date', ['attribute' => $attribute]));
                }
            }],
            'product_ids' => ['required_if:type,product_base', 'missing_unless:type,product_base', 'array', 'list', 'min:1', 'max:1000'],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('user_id', $this->user('store')?->id)],
            'min_buy' => ['required_if:type,cart_base', 'missing_unless:type,cart_base', 'numeric', 'min:1', 'max:999999999999999999.99'],
            'max_discount' => ['required_if:type,cart_base', 'missing_unless:type,cart_base', 'numeric', 'min:1', 'max:999999999999999999.99'],
        ];
    }

    /** @return array<string, mixed> Only columns written by the existing coupon controller. */
    public function couponAttributes(): array
    {
        $data = $this->validated();
        $dates = $this->dates() ?? throw new LogicException('Coupon dates must be validated first.');
        $details = $data['type'] === 'product_base'
            ? array_map(fn ($id) => ['product_id' => $id], $data['product_ids'])
            : ['min_buy' => $data['min_buy'], 'max_discount' => $data['max_discount']];

        return $this->safe()->only(['type', 'code', 'discount', 'discount_type']) + [
            'start_date' => $dates[0], 'end_date' => $dates[1], 'details' => json_encode($details, JSON_THROW_ON_ERROR),
        ];
    }

    /** @return array{int, int}|null Signed INT timestamps, in the date picker's existing format. */
    private function dates(): ?array
    {
        $value = $this->input('date_range');
        if (! is_string($value) || preg_match('/^\d{2}\/\d{2}\/\d{4} - \d{2}\/\d{2}\/\d{4}$/D', $value) !== 1) {
            return null;
        }
        [$start, $end] = explode(' - ', $value);
        $first = DateTimeImmutable::createFromFormat('!m/d/Y', $start);
        $last = DateTimeImmutable::createFromFormat('!m/d/Y', $end);
        if (! $first || ! $last || $first->format('m/d/Y') !== $start || $last->format('m/d/Y') !== $end) {
            return null;
        }
        $range = [$first->getTimestamp(), $last->getTimestamp()];

        return $range[0] < -2147483648 || $range[1] > 2147483647 || $range[1] < $range[0] ? null : $range;
    }
}
