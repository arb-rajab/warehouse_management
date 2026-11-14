<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesOptionalNote;
use App\Http\Requests\Concerns\ValidatesReceivedPallets;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT /api/v1/receipts/{receipt}/pallets`: the whole pallet list of a draft
 * receipt as written on the bill, replacing the one recorded before (`[]`
 * clears it), and optionally the receipt's note (sent null clears it,
 * omitted keeps it).
 */
class UpdateReceiptPalletsRequest extends FormRequest
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
            ...$this->receivedPalletsRules(true),
            ...$this->noteRules(),
        ];
    }

    /**
     * The receipt changes besides its pallets: the note, only when it was sent.
     *
     * @return array{note?: string|null}
     */
    public function receiptChanges(): array
    {
        return $this->exists('note') ? ['note' => $this->filled('note') ? (string) $this->string('note') : null] : [];
    }
}
