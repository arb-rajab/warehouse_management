<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesWebsiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSellerCommissionRequest extends FormRequest
{
    use ValidatesWebsiteSettings;

    public const KEYS = ['vendor_commission', 'minimum_seller_amount_withdraw'];

    /** Preserve the legacy writer's refusal of foreign/localized setting types. */
    public function authorize(): bool
    {
        $types = $this->input('types');

        return is_array($types) && $types !== [] && count(array_filter($types, fn (mixed $type): bool => is_string($type) && in_array($type, self::KEYS, true))) === count($types);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'types' => ['required', 'array', 'list', 'min:1'],
            'types.*' => ['required', 'string', 'distinct', Rule::in(self::KEYS)],
            'vendor_commission' => [Rule::requiredIf(fn (): bool => in_array('vendor_commission', $this->input('types', []), true)), 'numeric', 'between:0,100', 'decimal:0,2'],
            'minimum_seller_amount_withdraw' => [Rule::requiredIf(fn (): bool => in_array('minimum_seller_amount_withdraw', $this->input('types', []), true)), 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->refuseUnexpectedSettingInput($validator, ['types', ...array_values(array_filter($this->array('types'), is_string(...)))]));
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return $this->safe()->except('types');
    }
}
