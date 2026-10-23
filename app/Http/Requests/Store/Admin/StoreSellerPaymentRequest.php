<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreSellerPaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $withdrawal = Rule::exists('seller_withdraw_requests', 'id');
        if ($this->input('payment_withdraw') === 'withdraw_request' && in_array($this->input('payment_option'), ['cash', 'bank_payment'], true)) {
            $withdrawal->where(fn (Builder $query) => $query->whereIn('user_id', DB::table('shops')->select('user_id')->where('id', $this->integer('shop_id'))));
        }

        return [
            'shop_id' => ['required', 'integer', Rule::exists('shops', 'id')],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_option' => ['required', 'string', 'max:100'],
            'payment_withdraw' => ['nullable', 'in:withdraw_request'],
            'withdraw_request_id' => ['required_if:payment_withdraw,withdraw_request', 'nullable', 'integer', $withdrawal],
            'txn_code' => ['nullable', 'string', 'max:191'],
        ];
    }
}
