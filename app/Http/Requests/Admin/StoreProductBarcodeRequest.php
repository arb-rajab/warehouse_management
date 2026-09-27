<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductBarcodeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * `unique` is global across every product, not scoped to the one being
     * added to — a barcode identifies one physical product for scanning, so
     * the same code must never resolve to two different products (see the
     * `wms_product_barcodes` migration).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:64', 'unique:wms_product_barcodes,barcode'],
        ];
    }
}
