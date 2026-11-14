<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation + accessor for the bulk pallet corrections (bulk note,
 * product change): `pallets[]`, 1 to 500 entries of `{id, etag?}`, each id
 * once. A sent `etag` is checked like `If-Match` under the row locks.
 */
trait ValidatesPalletSelection
{
    /** How many pallets one bulk request may change. */
    public const int MAX_SELECTED_PALLETS = 500;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function palletSelectionRules(): array
    {
        return [
            /** The selected pallets, 1 to 500. */
            'pallets' => ['required', 'array', 'min:1', 'max:'.self::MAX_SELECTED_PALLETS],
            'pallets.*' => ['array'],
            /** A pallet id; each id at most once per request. */
            'pallets.*.id' => ['required', 'integer', 'distinct'],
            /** The pallet's `etag` as last read (quoted like the `ETag` header, or bare); omit to skip the check. */
            'pallets.*.etag' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * The selected pallets in the order sent, each etag without its quotes
     * (null when not sent).
     *
     * @return list<array{id: int, etag: string|null}>
     */
    public function selectedPallets(): array
    {
        return array_values(array_map(fn (array $pallet): array => [
            'id' => (int) $pallet['id'],
            'etag' => isset($pallet['etag']) ? trim($pallet['etag'], '"') : null,
        ], $this->array('pallets')));
    }
}
