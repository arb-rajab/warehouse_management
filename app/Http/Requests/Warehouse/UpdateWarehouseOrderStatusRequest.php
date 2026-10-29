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
            'status.required' => __('api.warehouse.status_required'),
            'status.in' => __('api.warehouse.status_required'),
            'otajerOrderID.required_if' => __('api.warehouse.order_id_required'),
        ];
    }

    protected function failureMessage(Validator $validator): string
    {
        return $validator->errors()->first();
    }
}
