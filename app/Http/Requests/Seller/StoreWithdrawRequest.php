<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'lt:1000000000000000000'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
