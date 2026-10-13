<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSellerCommissionActivationRequest extends FormRequest
{
    /** Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['vendor_commission_activation', 'category_wise_commission'])],
            'value' => ['required', 'boolean'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->all()), ['type', 'value', '_token']) !== []) {
                $validator->errors()->add('type', __('validation.prohibited', ['attribute' => 'type']));
            }
        }];
    }
}
