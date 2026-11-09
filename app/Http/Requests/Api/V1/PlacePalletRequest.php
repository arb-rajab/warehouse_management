<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ResolvesSlotFromCoordinates;
use App\Http\Requests\Concerns\ValidatesOptionalNote;
use App\Models\Pallet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/v1/pallets/{pallet}/place`: the slot an unplaced pallet goes to,
 * by coordinates (`row_letter`, `cell_number`, `flat_number`, with the
 * optional `warehouse_id` as on pallet store), and an optional note. Without
 * `warehouse_id` a row letter several warehouses use resolves in the
 * pallet's own warehouse (its receipt's) instead of being refused; an
 * explicit `warehouse_id` of another warehouse still ends in 409
 * `pallet_warehouse_mismatch` from the service.
 */
class PlacePalletRequest extends FormRequest
{
    use ResolvesSlotFromCoordinates;
    use ValidatesOptionalNote;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->coordinateRules(),
            ...$this->noteRules(),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->resolveSlot($validator, defaultWarehouseId: $this->unplacedPalletWarehouseId());
        });
    }

    /**
     * The warehouse an unplaced pallet was received into, or null for a
     * placed one (the service refuses it as `pallet_already_placed`).
     */
    private function unplacedPalletWarehouseId(): ?int
    {
        /** @var Pallet $pallet */
        $pallet = $this->route('pallet');

        if ($pallet->cell_id !== null) {
            return null;
        }

        return $pallet->loadMissing('receiptLine:id,receipt_id', 'receiptLine.receipt:id,warehouse_id')
            ->receiptLine?->receipt->warehouse_id;
    }
}
