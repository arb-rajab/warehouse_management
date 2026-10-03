<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateWarehouseOrderCustomerRequest extends WarehouseRequest
{
    protected int $failureStatus = 422;

    /**
     * Existence is checked in the controller, where an unknown id keeps
     * answering 404 as it did before this request existed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer'],
            'customer_id' => ['required', 'integer'],
        ];
    }
}
