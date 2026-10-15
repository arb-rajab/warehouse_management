<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Deleting selected orders from the all-orders list or, with
 * `return_to=trip_orders`, from the trip orders list it returns to.
 */
class BulkDeleteOrdersRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['id' => ['required', 'array', 'min:1', 'max:100'], 'id.*' => ['required', 'integer', 'distinct', 'exists:orders,id'], 'return_to' => ['nullable', 'string', Rule::in(['trip_orders'])]];
    }
}
