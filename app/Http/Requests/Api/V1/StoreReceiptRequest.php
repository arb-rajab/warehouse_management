<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesPalletContents;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/v1/receipts`: a draft receipt for one warehouse, with the
 * typed bill header and the lines copied from the bill.
 */
class StoreReceiptRequest extends FormRequest
{
    use ValidatesPalletContents;

    /** How many lines one bill may carry. */
    private const int MAX_LINES = 200;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** The warehouse the truck is received into (ids from `GET /api/v1/warehouses`). */
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            /** The bill's name or number. */
            'bill_name' => ['required', 'string', 'max:255'],
            /** Free text from the bill. */
            'bill_info' => ['nullable', 'string', 'max:5000'],
            /** The bill's lines: one product each. */
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*' => ['array'],
            'lines.*.product_id' => $this->palletProductRules(),
            /** Pallets of this product on the bill. */
            'lines.*.expected_pallets_count' => ['required', 'integer', 'min:1', 'max:1000'],
            /** Boxes per pallet on the bill. */
            'lines.*.expected_boxes' => ['required', 'integer', 'min:1', 'max:100000'],
            /** The expiry on the bill (`Y-m-d`); omit when it states none. */
            'lines.*.expected_expiration_date' => ['nullable', 'date'],
        ];
    }

    /**
     * The validated bill lines.
     *
     * @return list<array{product_id: int, expected_pallets_count: int, expected_boxes: int, expected_expiration_date: string|null}>
     */
    public function billLines(): array
    {
        return array_values(array_map(fn (array $line): array => [
            'product_id' => (int) $line['product_id'],
            'expected_pallets_count' => (int) $line['expected_pallets_count'],
            'expected_boxes' => (int) $line['expected_boxes'],
            'expected_expiration_date' => isset($line['expected_expiration_date']) ? (string) $line['expected_expiration_date'] : null,
        ], $this->array('lines')));
    }
}
