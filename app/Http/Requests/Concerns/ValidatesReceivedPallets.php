<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Shared validation + accessor for the pallets that actually arrived on a
 * receipt line (`pallets[]`, each with its `boxes` and optional
 * `expiration_date`), for checking a bill line and adding an extra one.
 */
trait ValidatesReceivedPallets
{
    /** How many pallets one line may record. */
    private const int MAX_RECEIVED_PALLETS = 500;

    /**
     * @param  int  $min  The fewest pallets accepted: 0 when an empty list means nothing arrived.
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function receivedPalletsRules(int $min): array
    {
        return [
            /** The pallets that arrived, each its own boxes and expiry. */
            'pallets' => ['present', 'array', 'min:'.$min, 'max:'.self::MAX_RECEIVED_PALLETS],
            'pallets.*' => ['array'],
            /** Boxes on this pallet. */
            'pallets.*.boxes' => ['required', 'integer', 'min:1', 'max:100000'],
            /** This pallet's expiry (`Y-m-d`); omit when it has none. */
            'pallets.*.expiration_date' => ['nullable', 'date'],
        ];
    }

    /**
     * The validated pallets, expiry dates normalized to `Y-m-d`.
     *
     * @return list<array{boxes: int, expiration_date: string|null}>
     */
    public function receivedPallets(): array
    {
        return array_values(array_map(fn (array $pallet): array => [
            'boxes' => (int) $pallet['boxes'],
            'expiration_date' => isset($pallet['expiration_date']) ? Carbon::parse($pallet['expiration_date'])->toDateString() : null,
        ], $this->array('pallets')));
    }
}
