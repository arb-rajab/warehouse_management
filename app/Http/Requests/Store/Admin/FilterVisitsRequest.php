<?php

namespace App\Http\Requests\Store\Admin;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterVisitsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->dateWindow() === null) {
                    $fail(__('validation.date', ['attribute' => $attribute]));
                }
            }],
        ];
    }

    /**
     * Accept the existing Blade date picker's format and ISO date links.
     *
     * @return array{string, string}|null
     */
    public function dateWindow(): ?array
    {
        $value = $this->input('date');
        if (! is_string($value)) {
            return null;
        }

        $dates = explode(' to ', $value);
        if (count($dates) !== 2) {
            return null;
        }

        if (! preg_match('/^([0-9]{2}-[0-9]{2}-[0-9]{4}|[0-9]{4}-[0-9]{2}-[0-9]{2}) to ([0-9]{2}-[0-9]{2}-[0-9]{4}|[0-9]{4}-[0-9]{2}-[0-9]{2})$/D', $value)) {
            return null;
        }

        foreach (['d-m-Y', 'Y-m-d'] as $format) {
            $start = DateTimeImmutable::createFromFormat('!'.$format, $dates[0]);
            $end = DateTimeImmutable::createFromFormat('!'.$format, $dates[1]);
            if ($start !== false && $end !== false
                && $start->format($format) === $dates[0] && $end->format($format) === $dates[1]
                && $start <= $end) {
                return [$start->format('Y-m-d').' 00:00:00', $end->format('Y-m-d').' 23:59:59'];
            }
        }

        return null;
    }
}
