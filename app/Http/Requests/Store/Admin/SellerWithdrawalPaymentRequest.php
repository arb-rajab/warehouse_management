<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SellerWithdrawalPaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', Rule::exists('users', 'id'), Rule::exists('shops', 'user_id')],
            'seller_withdraw_request_id' => ['required', 'integer', Rule::exists('seller_withdraw_requests', 'id')->where('user_id', $this->integer('id'))],
        ];
    }
}
