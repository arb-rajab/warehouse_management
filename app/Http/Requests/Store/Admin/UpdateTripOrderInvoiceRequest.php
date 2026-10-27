<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An order's invoice number, saved from the trip orders list on change: any
 * text up to the column's 255 characters, or empty to clear it.
 */
class UpdateTripOrderInvoiceRequest extends TripWriteRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'invoice_number' => ['nullable', 'string', 'max:255']];
    }
}
