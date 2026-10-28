<?php

namespace App\Http\Requests\Concerns;

use Closure;
use DateTimeImmutable;

trait ValidatesDiscountDateRange
{
    /**
     * A discount range as the legacy date-range picker posted it,
     * `d-m-Y H:i:s to d-m-Y H:i:s`: two real dates, the end not before the
     * start. ProductService and the digital product writer turn each half into
     * a timestamp with strtotime() in the app timezone.
     *
     * @return array<int, mixed>
     */
    protected function discountDateRangeRules(): array
    {
        return ['bail', 'nullable', 'string', 'max:42', function (string $attribute, mixed $value, Closure $fail): void {
            $dates = explode(' to ', $value);
            if (count($dates) !== 2 || preg_match('/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2} to \d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}$/D', $value) !== 1) {
                $fail(__('validation.date_format', ['attribute' => __('validation.attributes.date_range'), 'format' => 'd-m-Y H:i:s to d-m-Y H:i:s']));

                return;
            }
            $start = DateTimeImmutable::createFromFormat('!d-m-Y H:i:s', $dates[0]);
            $end = DateTimeImmutable::createFromFormat('!d-m-Y H:i:s', $dates[1]);
            if ($start === false || $end === false || $start->format('d-m-Y H:i:s') !== $dates[0] || $end->format('d-m-Y H:i:s') !== $dates[1] || $end < $start) {
                $fail(__('validation.date_format', ['attribute' => __('validation.attributes.date_range'), 'format' => 'd-m-Y H:i:s to d-m-Y H:i:s']));
            }
        }];
    }
}
