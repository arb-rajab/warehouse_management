<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Controllers\Store\Admin\TripOrderController;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The trip orders list's bulk "mark as confirmed / ready for delivery":
 * 1–100 distinct existing orders and one of the two statuses.
 */
class BulkUpdateTripOrdersRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'delivery_status' => ['required', 'string', Rule::in(TripOrderController::BULK_STATUSES)],
            'id' => ['required', 'array', 'min:1', 'max:100'],
            'id.*' => ['required', 'integer', 'distinct', Rule::exists('orders', 'id')],
        ];
    }
}
