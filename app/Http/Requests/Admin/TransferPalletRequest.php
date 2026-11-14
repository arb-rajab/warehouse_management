<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersByWarehouseIds;
use App\Http\Requests\Concerns\ResolvesSlotFromCoordinates;
use App\Http\Requests\Concerns\ValidatesOptionalNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The admin pallet transfer, from a row page's pallet dialog or the warehouse
 * map. The destination may be in another warehouse: `warehouse_id` names the
 * destination row's warehouse (see ResolvesSlotFromCoordinates). The map sends
 * `return_to=map` with its own `warehouse_ids[]`, so the redirect lands back
 * on the map with the same warehouse filter.
 */
class TransferPalletRequest extends FormRequest
{
    use FiltersByWarehouseIds;
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
            'return_to' => ['nullable', 'string', Rule::in(['map'])],
            ...$this->warehouseIdsFilterRules(),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->resolveTransferDestination($validator);
        });
    }

    /**
     * Whether the transfer came from the warehouse map and returns to it.
     */
    public function returnsToMap(): bool
    {
        return $this->input('return_to') === 'map';
    }
}
