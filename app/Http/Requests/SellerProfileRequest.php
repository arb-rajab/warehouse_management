<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Models\StoreUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SellerProfileRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected $errorBag = 'sellerProfile';

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        if (env('DEMO_MODE') === 'On') {
            return true;
        }

        return StoreUser::query()->findOrFail($this->route('id'))->is($this->user('store'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:20'],
            'new_password' => ['bail', 'nullable', 'required_with:confirm_password', 'string', 'min:6', 'max:72', 'regex:/\A[^\x00]{1,72}\z/', 'confirmed:confirm_password'],
            'confirm_password' => ['nullable', 'required_with:new_password', 'string'],
            'photo' => ['nullable', 'integer', Rule::exists('uploads', 'id')->where('user_id', $this->user('store')?->id)->where('type', 'image')],
            'cash_on_delivery_status' => ['sometimes', 'boolean'],
            'bank_payment_status' => ['sometimes', 'boolean'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_acc_name' => ['nullable', 'string', 'max:200'],
            'bank_acc_no' => ['nullable', 'string', 'max:50'],
            'bank_routing_no' => ['nullable', 'integer', 'between:-2147483648,2147483647'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required'         => translate('Name is required'),
            'new_password.min'      => translate('Minimum 6 characters'),
            'confirm_password.min'  => translate('Minimum 6 characters'),
        ];
    }
}
