<?php

namespace App\Http\Requests\Concerns;

use App\Models\Cell;
use App\Models\Row;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use RuntimeException;

/**
 * Shared handling for requests that address a slot by its human-readable
 * coordinates (row letter + cell number + flat number) rather than by id.
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
            $prefix.'row_letter' => ['required', 'string', 'max:2', 'exists:rows,letter'],
            $prefix.'cell_number' => ['required', 'integer', 'min:1'],
            $prefix.'flat_number' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Resolve the slot addressed by this request's coordinate fields, recording a
     * validation error against the cell-number field when no such slot exists.
     */
    protected function resolveSlot(Validator $validator, string $prefix = ''): ?Cell
    {
        if ($validator->errors()->has($prefix.'row_letter')) {
            return null;
        }

        $row = Row::query()->where('letter', $this->input($prefix.'row_letter'))->first();

        if ($row === null) {
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
     * The slot resolved from the request's coordinates. Only valid after successful validation.
     */
    public function resolvedSlot(): Cell
    {
        return $this->resolvedSlot ?? throw new RuntimeException('resolvedSlot() called before validation resolved it.');
    }
}
