<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Services\ShopVerificationStorage;
use App\Support\ShopVerificationForm;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreShopVerificationRequest extends FormRequest
{
    use RejectsUnexpectedInput { withValidator as private rejectUnexpectedInput; }

    protected $errorBag = 'sellerVerification';

    /** Get the validation rules that apply to the request.
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $rules = [];
        foreach (ShopVerificationForm::fields() as $key => $field) {
            $name = 'element_'.$key;
            $rules[$name] = match ($field->type) {
                'text' => ['required', 'string', 'max:10000'],
                'file' => ['bail', 'required', 'file', 'max:20480', function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        app(ShopVerificationStorage::class)->validatedExtension($value);
                    } catch (ValidationException $exception) {
                        $fail($exception->errors()['verification_file'][0]);
                    }
                }],
                'multi_select' => ['required', 'array', 'list', 'max:100'],
                default => ['required', 'string', Rule::in(json_decode($field->options))],
            };
            if ($field->type === 'multi_select') {
                $rules[$name.'.*'] = ['required', 'string', 'distinct', Rule::in(json_decode($field->options))];
            }
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectUnexpectedInput($validator);
        $validator->after(function (Validator $validator): void {
            if (ShopVerificationForm::fields() === []) {
                $validator->errors()->add('verification_form', __('seller.shop.verification_unavailable'));
            }
        });
    }
}
