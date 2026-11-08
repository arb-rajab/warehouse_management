<?php

namespace App\Http\Requests\Admin;

use App\Rules\NewProductBarcode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductBarcodeRequest extends FormRequest
{
    /**
     * Trimmed before validation, so the uniqueness check compares what is
     * actually stored and a scanner's trailing whitespace can't slip a
     * duplicate past it.
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
     * The WMS keeps its own limits: printable ASCII (what a Code 128 label
     * can draw) up to 64 characters. NewProductBarcode adds the shared rules:
     * no product, this one included, may already hold the value anywhere in
     * the one barcode table (`product_barcodes`, shared with the store), so
     * one scan never resolves to two products; and the value may not be an
     * existing product's id, since every product already carries its id as
     * an undeletable default barcode. The controller repeats the check under
     * the shared lock before inserting.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::barcodeRules();
    }

    /**
     * The rules, also run again by Admin\ProductController::storeBarcode()
     * under NewProductBarcode::locked().
     *
     * @return array<string, list<mixed>>
     */
    public static function barcodeRules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:64', 'regex:/^[\x20-\x7E]+$/', new NewProductBarcode],
        ];
    }
}
