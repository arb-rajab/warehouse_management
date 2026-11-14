<?php

namespace App\Http\Requests\Concerns;

use App\Models\Cell;
use App\Models\Pallet;
use App\Models\Row;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Shared handling for requests that address a slot by its human-readable
 * coordinates (row letter + cell number + flat number) rather than by id.
 *
 * A row letter is unique per warehouse only, so an optional
 * `{prefix}warehouse_id` picks the warehouse; it may be omitted while just
 * one warehouse uses the letter (every request from before warehouses
 * existed), and is required, with an error on that field, once several do.
 */
trait ResolvesSlotFromCoordinates
{
    private ?Cell $resolvedSlot = null;

    /**
     * Validation rules for a set of coordinate fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function coordinateRules(string $prefix = ''): array
    {
        return [
            /** The row's warehouse. Optional while only one warehouse uses `row_letter`; required (422 on this field) once several do. */
            $prefix.'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            $prefix.'row_letter' => ['required', 'string', 'max:2', 'exists:rows,letter'],
            $prefix.'cell_number' => ['required', 'integer', 'min:1'],
            $prefix.'flat_number' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Resolve the slot addressed by this request's coordinate fields, recording a
     * validation error against the cell-number field when no such slot exists.
     *
     * `$defaultWarehouseId` settles a letter several warehouses use when the
     * caller sent no `{prefix}warehouse_id` (placing an unplaced pallet passes
     * the pallet's own warehouse); a letter just one warehouse uses resolves
     * to that row as before, whatever the default.
     */
    protected function resolveSlot(Validator $validator, string $prefix = '', ?int $defaultWarehouseId = null): ?Cell
    {
        if ($validator->errors()->hasAny([$prefix.'row_letter', $prefix.'warehouse_id'])) {
            return null;
        }

        $rows = Row::query()
            ->where('letter', $this->input($prefix.'row_letter'))
            ->when($this->filled($prefix.'warehouse_id'), fn ($query) => $query->where('warehouse_id', $this->integer($prefix.'warehouse_id')))
            ->limit(2)
            ->get();

        if ($rows->count() > 1 && $defaultWarehouseId !== null) {
            $rowInDefault = Row::query()
                ->where('letter', $this->input($prefix.'row_letter'))
                ->where('warehouse_id', $defaultWarehouseId)
                ->first();

            if ($rowInDefault !== null) {
                $rows = new Collection([$rowInDefault]);
            }
        }

        if ($rows->count() > 1) {
            $validator->errors()->add($prefix.'warehouse_id', __('messages.row_letter_in_several_warehouses'));

            return null;
        }

        $row = $rows->first();

        if ($row === null) {
            $validator->errors()->add($prefix.'cell_number', __('messages.slot_does_not_exist'));

            return null;
        }

        $slot = Cell::query()
            ->atCoordinates(
                $row,
                $this->integer($prefix.'cell_number'),
                $this->integer($prefix.'flat_number'),
            )
            ->first();

        if ($slot === null) {
            $validator->errors()->add($prefix.'cell_number', __('messages.slot_does_not_exist'));

            return null;
        }

        $this->resolvedSlot = $slot;

        return $slot;
    }

    /**
     * Resolve the transfer destination addressed by this request's coordinate
     * fields, rejecting a destination the route-bound pallet already sits in —
     * transferring a pallet onto itself is a no-op the caller almost certainly
     * didn't mean.
     */
    protected function resolveTransferDestination(Validator $validator, string $prefix = ''): void
    {
        $destination = $this->resolveSlot($validator, $prefix);

        if ($destination === null) {
            return;
        }

        /** @var Pallet $pallet */
        $pallet = $this->route('pallet');

        if ($destination->id === $pallet->cell_id) {
            $validator->errors()->add($prefix.'cell_number', __('messages.pallet_already_at_location'));
        }
    }

    /**
     * The slot resolved from the request's coordinates. Only valid after successful validation.
     */
    public function resolvedSlot(): Cell
    {
        return $this->resolvedSlot ?? throw new RuntimeException('resolvedSlot() called before validation resolved it.');
    }
}
