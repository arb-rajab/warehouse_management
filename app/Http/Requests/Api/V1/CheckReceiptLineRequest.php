<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesOptionalNote;
use App\Http\Requests\Concerns\ValidatesReceivedPallets;
use App\Models\ReceiptLine;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT /api/v1/receipts/{receipt}/lines/{line}/check`: the pallets of the
 * line that actually arrived, replacing any recorded before, and an optional
 * note (omitting it clears the line's note). An empty `pallets` means nothing
 * of a bill line arrived; an extra line needs at least one pallet (delete it
 * instead).
 */
class CheckReceiptLineRequest extends FormRequest
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
        /** @var ReceiptLine $line */
        $line = $this->route('line');

        return [
            ...$this->receivedPalletsRules($line->is_extra ? 1 : 0),
            ...$this->noteRules(),
        ];
    }
}
