<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesOptionalNote;
use App\Http\Requests\Concerns\ValidatesPalletContents;
use App\Http\Requests\Concerns\ValidatesReceivedPallets;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/v1/receipts/{receipt}/lines`: an extra line for pallets that
 * arrived without being on the bill — its product, at least one pallet, and
 * an optional note.
 */
class StoreReceiptLineRequest extends FormRequest
{
    use ValidatesOptionalNote;
    use ValidatesPalletContents;
    use ValidatesReceivedPallets;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => $this->palletProductRules(),
            ...$this->receivedPalletsRules(1),
            ...$this->noteRules(),
        ];
    }
}
