<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateWarehouseCustomerRequest extends WarehouseRequest
{
    /**
     * Every field is `sometimes`, so a partial update only touches what it
     * sends; `nullable` lets the client clear one deliberately, except `name` and
     * `company_name`, whose columns are NOT NULL. Lengths follow the columns. The trade
     * licence is limited to what TradeLicenseStorage keeps as a PDF or PNG/JPEG
     * (it refuses BMP, and Word documents are not accepted from this client).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'nullable', 'string', 'max:30'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'company_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'shipping_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'trade_license' => ['sometimes', 'nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:20000'],
        ];
    }
}
