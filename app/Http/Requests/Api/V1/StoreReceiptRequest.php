<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesOptionalNote;
use App\Http\Requests\Concerns\ValidatesReceivedPallets;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/v1/receipts`: a draft receipt for one warehouse, with an
 * optional note and, optionally already, the pallets as written on the bill.
 */
class StoreReceiptRequest extends FormRequest
{
    use ValidatesOptionalNote;
    use ValidatesReceivedPallets;

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
            ...$this->noteRules(),
            ...$this->receivedPalletsRules(false),
        ];
    }
}
