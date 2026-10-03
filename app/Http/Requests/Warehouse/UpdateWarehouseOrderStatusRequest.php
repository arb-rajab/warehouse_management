<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;

class UpdateWarehouseOrderStatusRequest extends WarehouseRequest
{
    /**
     * `id` is deliberately not validated: an unknown or missing one has always
     * answered with the 'Order not found' envelope, which the client keys on.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:fetched,sent'],
            'otajerOrderID' => ['required_if:status,sent', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'status must be `fetched` or `sent` ',
            'status.in' => 'status must be `fetched` or `sent` ',
            'otajerOrderID.required_if' => 'otajerOrderID is missing in the request body',
        ];
    }

    protected function failureMessage(Validator $validator): string
    {
        return $validator->errors()->first();
    }
}
