<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Closure;
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
     * A barcode may also never equal a product id: every product already
     * carries its id as an undeletable default barcode, so a custom barcode
     * reading the same would resolve to two products (or to one twice).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'barcode' => [
                'required', 'string', 'max:64', 'regex:/^[\x20-\x7E]+$/', 'unique:wms_product_barcodes,barcode',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->isProductId((string) $value)) {
                        $fail('messages.barcode_is_product_id')->translate();
                    }
                },
            ],
        ];
    }

    /**
     * Only the canonical form of an id ("7", not "007" or "7.0") collides:
     * the default barcode is the id as plain digits, so that is the only
     * string a scan can match it by.
     */
    private function isProductId(string $value): bool
    {
        if (! preg_match('/^[1-9][0-9]{0,17}$/', $value)) {
            return false;
        }

        return Product::query()->whereKey((int) $value)->exists();
    }
}
