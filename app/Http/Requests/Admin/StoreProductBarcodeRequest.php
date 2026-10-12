<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductBarcodeRequest extends FormRequest
{
    /**
     * Trimmed before validation, so `unique` compares what is actually stored
     * and a scanner's trailing whitespace can't slip a duplicate past it.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('barcode'))) {
            $this->merge(['barcode' => trim($this->input('barcode'))]);
        }
    }

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
